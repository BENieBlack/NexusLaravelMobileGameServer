<?php

namespace App\Console\Commands\Wallet;

use App\Repositories\Mst\MstItemRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;

/**
 * MigrateItemsCommand
 *
 * Wallet管理に切り替えたアイテムの残高を trx_item から trx_wallet へ移す
 *
 * mst_item.is_wallet を後から立てると、それまでに配ったぶんは
 * trx_item に残ったままになり、プレイヤーからは消えたように見える。
 * このコマンドで現在残高を Wallet へ移し、移し終えた trx_item の行を消す。
 *
 * trx はプレイヤーごとにシャードへ分かれているため、
 * config('database.pitr.active_trx_connections') の全シャードを走査する。
 *
 * trx_item に有効期限は無いので、移した残高は無期限として入れる。
 */
class MigrateItemsCommand extends Command
{
    /**
     * コマンドのシグネチャ
     *
     * @var string
     */
    protected $signature = 'wallet:migrate-items
                            {--dry-run : 実行結果をシミュレートのみ（実際には移さない）}
                            {--item= : 特定のmst_item.idのみ処理}';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = 'Wallet管理に切り替えたアイテムの残高を trx_item から trx_wallet へ移す';

    public function __construct(
        private readonly MstItemRepository $mstItemRepository,
    ) {
        parent::__construct();
    }

    /**
     * コマンド実行
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $targetItemId = $this->option('item');

        if ($isDryRun) {
            $this->warn('[DRY RUN モード] 実際には移しません');
        }

        $walletItemIds = $this->resolveTargetItemIds($targetItemId);

        if ($walletItemIds === []) {
            $this->info('Wallet管理のアイテムが見つかりませんでした');

            return self::SUCCESS;
        }

        $this->info('対象アイテム: '.implode(', ', $walletItemIds));

        $movedRows = 0;
        $movedAmount = 0;

        foreach ($this->trxConnections() as $connection) {
            $rows = DB::connection($connection)->table('trx_item')
                ->whereIn('mst_item_id', $walletItemIds)
                ->where('is_delete', false)
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $this->line("{$connection}: {$rows->count()} 件");

            foreach ($rows as $row) {
                $movedAmount += (int) $row->free_amount + (int) $row->paid_amount;
                $movedRows++;

                if ($isDryRun) {
                    continue;
                }

                $this->moveToWallet($connection, $row);
            }
        }

        if ($movedRows === 0) {
            $this->info('移す対象の残高はありませんでした');

            return self::SUCCESS;
        }

        $this->info("移行: {$movedRows} 件（合計 {$movedAmount}）");

        return self::SUCCESS;
    }

    /**
     * 処理対象のアイテムIDを決める
     *
     * @return list<string>
     */
    private function resolveTargetItemIds(?string $targetItemId): array
    {
        if ($targetItemId !== null) {
            if (! $this->mstItemRepository->isWalletManaged($targetItemId)) {
                $this->error("Wallet管理のアイテムではありません: {$targetItemId}");

                return [];
            }

            return [$targetItemId];
        }

        return $this->mstItemRepository->selectWalletManaged()
            ->map(fn ($item) => (string) $item->getAttribute('id'))
            ->values()
            ->all();
    }

    /**
     * 1件ぶんの残高をWalletへ移し、元の行を消す
     *
     * 同じシャード内で完結するため、まとめてトランザクションに入れる。
     * 途中で落ちると残高が二重に見えるか消えるため、片方だけ通してはいけない。
     */
    private function moveToWallet(string $connection, object $row): void
    {
        $now = ClockUtility::nowToString();
        $freeAmount = (int) $row->free_amount;
        $paidAmount = (int) $row->paid_amount;

        DB::connection($connection)->transaction(function () use ($connection, $row, $now, $freeAmount, $paidAmount) {
            // 現在値。既にWalletに残高があれば足し込む
            $wallet = DB::connection($connection)->table('trx_wallet')
                ->where('sys_player_id', $row->sys_player_id)
                ->where('mst_item_id', $row->mst_item_id)
                ->first();

            if ($wallet === null) {
                DB::connection($connection)->table('trx_wallet')->insert([
                    'sys_player_id' => $row->sys_player_id,
                    'mst_item_id' => $row->mst_item_id,
                    'free_amount' => $freeAmount,
                    'paid_amount' => $paidAmount,
                    'is_delete' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::connection($connection)->table('trx_wallet')
                    ->where('sys_player_id', $row->sys_player_id)
                    ->where('mst_item_id', $row->mst_item_id)
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
                    'sys_player_id' => $row->sys_player_id,
                    'mst_item_id' => $row->mst_item_id,
                    'is_paid' => (bool) $isPaid,
                    'current_amount' => $amount,
                    'initial_amount' => $amount,
                    'expire_at' => null,
                    'is_delete' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            // 移し終えた行は残さない。残すと同じ残高が2箇所に見える
            DB::connection($connection)->table('trx_item')
                ->where('sys_player_id', $row->sys_player_id)
                ->where('mst_item_id', $row->mst_item_id)
                ->delete();
        });
    }

    /**
     * 走査するTrxDBの接続名
     *
     * @return list<string>
     */
    private function trxConnections(): array
    {
        /** @var list<string> $connections */
        $connections = config('database.pitr.active_trx_connections', ['trx1']);

        return $connections;
    }
}
