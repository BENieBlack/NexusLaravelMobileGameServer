<?php

namespace App\Console\Commands\Mailbox;

use App\Models\Trx\TrxMailbox;
use App\Repositories\Trx\TrxMailboxRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use NexusUnitOfWork\Contracts\QueryManagerInterface;

/**
 * DeleteExpiredCommand
 *
 * 期限切れメールを自動削除するバッチコマンド
 *
 * trx_mailbox はプレイヤーごとにシャードへ分かれているため、
 * TrxMailbox の既定接続（trx1）だけを見ると他シャードのメールが消えない。
 * config('database.pitr.active_trx_connections') の全シャードを走査する。
 */
class DeleteExpiredCommand extends Command
{
    /**
     * コマンドのシグネチャ
     *
     * @var string
     */
    protected $signature = 'mailbox:delete-expired
                            {--dry-run : 実行結果をシミュレートのみ（実際には削除しない）}
                            {--player-id= : 特定プレイヤーのみ処理}
                            {--limit=1000 : 一度に処理する最大件数（全シャード合計）}';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = '期限切れメールを自動削除（保護されたメールは除外）';

    /**
     * コンストラクタ
     */
    public function __construct(
        private TrxMailboxRepository $trxMailboxRepository,
        private QueryManagerInterface $queryManager,
    ) {
        parent::__construct();
    }

    /**
     * コマンド実行
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $playerId = $this->option('player-id');
        $limit = (int) $this->option('limit');

        $this->info('期限切れメール削除バッチを開始します');
        $this->info('実行時刻: '.Carbon::now()->toDateTimeString());

        if ($isDryRun) {
            $this->warn('[DRY RUN モード] 実際には削除しません');
        }

        if ($playerId) {
            $this->info("対象プレイヤー: {$playerId}");
        } else {
            $this->info('対象: 全プレイヤー');
        }

        $this->info("最大処理件数: {$limit}");
        $this->newLine();

        // シャードごとに期限切れメールを取得する（--limitは全シャードの合計）
        $expiredByConnection = $this->selectExpiredMailboxesByConnection($playerId, $limit);
        $totalCount = array_sum(array_map(
            fn (Collection $mailboxes) => $mailboxes->count(),
            $expiredByConnection
        ));

        if ($totalCount === 0) {
            $this->info('期限切れメールは見つかりませんでした');

            return Command::SUCCESS;
        }

        $this->info("期限切れメールが {$totalCount} 件見つかりました");

        // プログレスバー表示
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $deletedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($expiredByConnection as $connection => $expiredMailboxes) {
            // Repositoryは接続ごとに書き込み先が決まるので、シャードを跨ぐ前に切り替える
            $this->trxMailboxRepository->setConnection($connection);
            $deletedInShard = 0;

            foreach ($expiredMailboxes as $mailbox) {
                try {
                    // 保護されているメールはスキップ
                    if ($mailbox->getIsProtected()) {
                        $skippedCount++;
                        $bar->advance();

                        continue;
                    }

                    // 既に削除済みならスキップ
                    if ($mailbox->getIsDelete()) {
                        $skippedCount++;
                        $bar->advance();

                        continue;
                    }

                    // Dry runでなければ削除
                    if (! $isDryRun) {
                        $mailbox->setIsDelete(true);
                        $this->trxMailboxRepository->setModel($mailbox);
                    }

                    $deletedCount++;
                    $deletedInShard++;
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("\nエラー: メールID {$mailbox->getId()} - {$e->getMessage()}");
                }

                $bar->advance();
            }

            // setModel()はキューに積むだけなので、次のシャードへ移る前に書き込む
            if (! $isDryRun && $deletedInShard > 0) {
                $this->queryManager->flush();
            }

            // 前のシャードのモデルを持ち越さない
            $this->trxMailboxRepository->forgetCachedModels();
        }

        $bar->finish();
        $this->newLine(2);

        // 結果表示
        $this->info('=== 処理結果 ===');
        $this->info("削除: {$deletedCount} 件");
        $this->info("スキップ: {$skippedCount} 件");

        if ($errorCount > 0) {
            $this->error("エラー: {$errorCount} 件");
        }

        if ($isDryRun) {
            $this->warn('[DRY RUN モード] 実際には削除されていません');
        }

        $this->newLine();
        $this->info('完了');

        return Command::SUCCESS;
    }

    /**
     * シャードごとに期限切れメールを取得する
     *
     * $limit は全シャードの合計として扱い、埋まった時点で以降のシャードは見ない。
     *
     * @return array<string, Collection<int, TrxMailbox>> 接続名 => メール一覧（空のシャードは含まない）
     */
    private function selectExpiredMailboxesByConnection(?int $playerId, int $limit): array
    {
        $result = [];
        $remaining = $limit;

        foreach ($this->trxConnections() as $connection) {
            if ($remaining <= 0) {
                break;
            }

            $query = TrxMailbox::on($connection)
                ->where('is_delete', false)
                ->where('is_protected', false)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', Carbon::now())
                ->limit($remaining);

            if ($playerId !== null) {
                $query->where('sys_player_id', $playerId);
            }

            $mailboxes = $query->get();

            if ($mailboxes->isNotEmpty()) {
                $result[$connection] = $mailboxes;
                $remaining -= $mailboxes->count();
            }
        }

        return $result;
    }

    /**
     * 走査対象のTrxDB接続を返す
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
