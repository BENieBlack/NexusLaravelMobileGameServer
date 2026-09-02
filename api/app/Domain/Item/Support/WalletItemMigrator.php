<?php

namespace App\Domain\Item\Support;

use App\Repositories\Trx\TrxItemRepository;
use App\Repositories\Trx\TrxWalletBalanceRepository;
use App\Repositories\Trx\TrxWalletRepository;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;

/**
 * WalletItemMigrator
 *
 * mst_item.is_wallet を後から立てたアイテムについて、
 * trx_item に残っている残高を Wallet へ移す。
 *
 * フラグを立てた瞬間から読み書きの先が Wallet に変わるため、
 * 移し終えるまでの間はプレイヤーから残高が消えて見える。
 * 触られた時点でその場で移せば、この窓そのものが無くなり、
 * 切り替えにメンテナンスが要らなくなる。
 *
 * 一括移行コマンド（wallet:migrate-items）もここを通る。
 * 移し方が2つあると片方だけ直したときに壊れるため、実装は1つにする。
 *
 * 移動はUnitOfWorkのキューではなく、その場のトランザクションで確定させる。
 * 直接DBを書くためServices配下ではなくSupport配下に置く。
 * キューに積むとフラッシュまでDBに反映されないため、
 * 同じリクエストで続けて消費したときに取得単位が読めず、
 * 残高があるのに何も消費されないまま通ってしまう。
 */
class WalletItemMigrator
{
    /**
     * このリクエストで移し終えた (プレイヤー, アイテム) の組
     *
     * @var array<string, true>
     */
    private array $migrated = [];

    public function __construct(
        private readonly TrxItemRepository $trxItemRepository,
        private readonly TrxWalletRepository $trxWalletRepository,
        private readonly TrxWalletBalanceRepository $trxWalletBalanceRepository,
    ) {}

    /**
     * trx_item に残高が残っていれば Wallet へ移す
     *
     * Wallet管理のアイテムを読み書きする前に呼ぶ。
     * 残っていなければ何もしないので、通常時の負荷は問い合わせ1回。
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $mstItemId  mst_item.id（Wallet管理のもの）
     */
    public function migrate(int $sysPlayerId, string $mstItemId): void
    {
        $key = $sysPlayerId.':'.$mstItemId;

        if (isset($this->migrated[$key])) {
            return;
        }

        // 見に行った時点で済みとして扱う。行が無かった場合も同じ
        $this->migrated[$key] = true;

        $moved = $this->moveRow($this->trxItemRepository->getConnection(), $sysPlayerId, $mstItemId);

        if ($moved === null) {
            return;
        }

        // 読み取りキャッシュに移行前の状態が残っていると、
        // このあとの加算や消費が古い残高を元に上書きしてしまう
        $this->trxItemRepository->forgetCachedModels();
        $this->trxWalletRepository->forgetCachedModels();
        $this->trxWalletBalanceRepository->forgetCachedModels();
    }

    /**
     * 1件ぶんの残高をWalletへ移し、元の行を消す
     *
     * 同じシャード内で完結するため、まとめてトランザクションに入れる。
     * 途中で落ちると残高が二重に見えるか消えるため、片方だけ通してはいけない。
     *
     * 金額はトランザクション内で読み直す。外で読んだ値を使うと、
     * 読み出しから削除までの間に増減したぶんが消えるか二重になる。
     *
     * trx_item に有効期限は無いので、移した残高は無期限として入れる。
     *
     * @return int|null 移した合計。対象の行が無かった場合は null
     */
    public function moveRow(string $connection, int $sysPlayerId, string $mstItemId): ?int
    {
        $now = ClockUtility::nowToString();

        return DB::connection($connection)->transaction(function () use ($connection, $sysPlayerId, $mstItemId, $now): ?int {
            $item = DB::connection($connection)->table('trx_item')
                ->where('sys_player_id', $sysPlayerId)
                ->where('mst_item_id', $mstItemId)
                ->lockForUpdate()
                ->first();

            // 読み出してからロックを取るまでに消えた／論理削除された
            if ($item === null || (bool) $item->is_delete) {
                return null;
            }

            $freeAmount = (int) $item->free_amount;
            $paidAmount = (int) $item->paid_amount;

            if ($freeAmount > 0 || $paidAmount > 0) {
                $this->addToWallet($connection, $sysPlayerId, $mstItemId, $freeAmount, $paidAmount, $now);
            }

            // 残高が0でも行は消す。残すと読むたびにここを通る
            DB::connection($connection)->table('trx_item')
                ->where('sys_player_id', $sysPlayerId)
                ->where('mst_item_id', $mstItemId)
                ->delete();

            return $freeAmount + $paidAmount;
        });
    }

    /**
     * 現在値と取得単位の両方へ加算する
     *
     * 既にWalletに残高があれば足し込む。上書きしてはいけない。
     */
    private function addToWallet(
        string $connection,
        int $sysPlayerId,
        string $mstItemId,
        int $freeAmount,
        int $paidAmount,
        string $now,
    ): void {
        $wallet = DB::connection($connection)->table('trx_wallet')
            ->where('sys_player_id', $sysPlayerId)
            ->where('mst_item_id', $mstItemId)
            ->lockForUpdate()
            ->first();

        if ($wallet === null) {
            DB::connection($connection)->table('trx_wallet')->insert([
                'sys_player_id' => $sysPlayerId,
                'mst_item_id' => $mstItemId,
                'free_amount' => $freeAmount,
                'paid_amount' => $paidAmount,
                'is_delete' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::connection($connection)->table('trx_wallet')
                ->where('sys_player_id', $sysPlayerId)
                ->where('mst_item_id', $mstItemId)
                ->update([
                    'free_amount' => (int) $wallet->free_amount + $freeAmount,
                    'paid_amount' => (int) $wallet->paid_amount + $paidAmount,
                    'updated_at' => $now,
                ]);
        }

        // 取得単位の残高。trx_item に有効期限は無いので無期限で入れる
        foreach ([false => $freeAmount, true => $paidAmount] as $isPaid => $amount) {
            if ($amount <= 0) {
                continue;
            }

            DB::connection($connection)->table('trx_wallet_balance')->insert([
                'sys_player_id' => $sysPlayerId,
                'mst_item_id' => $mstItemId,
                'is_paid' => (bool) $isPaid,
                'current_amount' => $amount,
                'initial_amount' => $amount,
                'expire_at' => null,
                'is_delete' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
