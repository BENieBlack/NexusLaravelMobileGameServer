<?php

namespace App\Console\Commands\Wallet;

use App\Domain\Item\Support\WalletItemMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
 *
 * 移す処理そのものは WalletItemMigrator にある。プレイヤーが触った時点で
 * その場で移す経路と同じ実装を使う。移し方が2つあると片方だけ直したときに壊れる。
 *
 * 手順は docs/wallet_managed_item_migration.md を参照。
 * メンテナンス中に実行する前提で、稼働中の実行は想定していない。
 */
class MigrateItemsCommand extends Command
{
    /**
     * 1度に読み出す行数の既定値
     */
    private const DEFAULT_CHUNK_SIZE = 1000;

    /**
     * コマンドのシグネチャ
     *
     * @var string
     */
    protected $signature = 'wallet:migrate-items
                            {--dry-run : 実行結果をシミュレートのみ（実際には移さない）}
                            {--item= : 特定のmst_item.idのみ処理}
                            {--chunk= : 1度に読み出す行数（既定: 1000）}';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = 'Wallet管理に切り替えたアイテムの残高を trx_item から trx_wallet へ移す';

    public function __construct(
        private readonly WalletItemMigrator $walletItemMigrator,
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
        $chunkSize = $this->resolveChunkSize();

        if ($chunkSize === null) {
            return self::FAILURE;
        }

        if ($isDryRun) {
            $this->warn('[DRY RUN モード] 実際には移しません');
        }

        $walletItemIds = $this->resolveTargetItemIds(
            is_string($targetItemId) ? $targetItemId : null
        );

        // 指定が不正だった場合。対象が0件なのとは区別する
        if ($walletItemIds === null) {
            return self::FAILURE;
        }

        if ($walletItemIds === []) {
            $this->info('Wallet管理のアイテムが見つかりませんでした');

            return self::SUCCESS;
        }

        $this->info('対象アイテム: '.implode(', ', $walletItemIds));

        $movedRows = 0;
        $movedAmount = 0;
        $skippedRows = 0;

        foreach ($this->trxConnections() as $connection) {
            $result = $this->migrateShard($connection, $walletItemIds, $isDryRun, $chunkSize);

            $movedRows += $result['rows'];
            $movedAmount += $result['amount'];
            $skippedRows += $result['skipped'];
        }

        if ($skippedRows > 0) {
            // 稼働中に流すとここに入る。残高がズレている可能性があるため黙って通さない
            $this->warn("移行中に変化した行をスキップしました: {$skippedRows} 件");
        }

        if ($movedRows === 0) {
            $this->info('移す対象の残高はありませんでした');

            return self::SUCCESS;
        }

        $this->info("移行: {$movedRows} 件（合計 {$movedAmount}）");

        return self::SUCCESS;
    }

    /**
     * 1シャードぶんを移す
     *
     * 主キー (sys_player_id, mst_item_id) を辿って少しずつ読む。
     * 全件を一度にメモリへ載せると、対象行が多いときに落ちる。
     *
     * @param  list<string>  $walletItemIds
     * @return array{rows: int, amount: int, skipped: int}
     */
    private function migrateShard(string $connection, array $walletItemIds, bool $isDryRun, int $chunkSize): array
    {
        $movedRows = 0;
        $movedAmount = 0;
        $skippedRows = 0;

        // 直前に読んだ行の主キー。移した行は消えるが、
        // dry-runでは消えないので、どちらでも進むように主キーで辿る
        $lastPlayerId = 0;
        $lastItemId = '';

        while (true) {
            $rows = DB::connection($connection)->table('trx_item')
                ->whereIn('mst_item_id', $walletItemIds)
                ->where('is_delete', false)
                ->where(function ($query) use ($lastPlayerId, $lastItemId) {
                    $query->where('sys_player_id', '>', $lastPlayerId)
                        ->orWhere(function ($builder) use ($lastPlayerId, $lastItemId) {
                            $builder->where('sys_player_id', $lastPlayerId)
                                ->where('mst_item_id', '>', $lastItemId);
                        });
                })
                ->orderBy('sys_player_id')
                ->orderBy('mst_item_id')
                ->limit($chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            foreach ($rows as $row) {
                $lastPlayerId = (int) $row->sys_player_id;
                $lastItemId = (string) $row->mst_item_id;

                if ($isDryRun) {
                    $movedAmount += (int) $row->free_amount + (int) $row->paid_amount;
                    $movedRows++;

                    continue;
                }

                $moved = $this->walletItemMigrator->moveRow(
                    $connection,
                    (int) $row->sys_player_id,
                    (string) $row->mst_item_id
                );

                if ($moved === null) {
                    $skippedRows++;

                    continue;
                }

                $movedAmount += $moved;
                $movedRows++;
            }
        }

        if ($movedRows > 0 || $skippedRows > 0) {
            $this->line("{$connection}: {$movedRows} 件");
        }

        return ['rows' => $movedRows, 'amount' => $movedAmount, 'skipped' => $skippedRows];
    }

    /**
     * 処理対象のアイテムIDを決める
     *
     * マスターは mst DB を直接読む。Repository経由だと Redis の
     * マスターキャッシュ（TTL 1時間）越しになり、is_wallet を立てた直後は
     * まだ false に見えて、1件も移さないまま成功扱いで終わってしまう。
     *
     * @return list<string>|null 指定が不正な場合は null
     */
    private function resolveTargetItemIds(?string $targetItemId): ?array
    {
        if ($targetItemId !== null) {
            $isWallet = DB::connection('mst')->table('mst_item')
                ->where('id', $targetItemId)
                ->value('is_wallet');

            if ($isWallet === null) {
                $this->error("マスターに存在しないアイテムです: {$targetItemId}");

                return null;
            }

            if (! (bool) $isWallet) {
                $this->error("Wallet管理のアイテムではありません: {$targetItemId}");

                return null;
            }

            return [$targetItemId];
        }

        /** @var list<string> $ids */
        $ids = DB::connection('mst')->table('mst_item')
            ->where('is_wallet', true)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return $ids;
    }

    /**
     * 1度に読み出す行数
     *
     * @return int|null 指定が不正な場合は null
     */
    private function resolveChunkSize(): ?int
    {
        $chunk = $this->option('chunk');

        if ($chunk === null) {
            return self::DEFAULT_CHUNK_SIZE;
        }

        if (! is_numeric($chunk) || (int) $chunk < 1) {
            $this->error("--chunk は1以上の整数で指定してください: {$chunk}");

            return null;
        }

        return (int) $chunk;
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
