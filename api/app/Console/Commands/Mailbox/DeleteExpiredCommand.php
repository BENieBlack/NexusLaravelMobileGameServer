<?php

namespace App\Console\Commands\Mailbox;

use App\Repositories\Trx\TrxMailboxRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * DeleteExpiredCommand
 *
 * 期限切れメールを自動削除するバッチコマンド
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
                            {--limit=1000 : 一度に処理する最大件数}';

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
    ) {
        parent::__construct();
    }

    /**
     * コマンド実行
     *
     * @return int
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $playerId = $this->option('player-id');
        $limit = (int)$this->option('limit');

        $this->info('期限切れメール削除バッチを開始します');
        $this->info('実行時刻: ' . Carbon::now()->toDateTimeString());
        
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

        // 期限切れメールを取得
        $expiredMailboxes = $this->getExpiredMailboxes($playerId, $limit);
        $totalCount = $expiredMailboxes->count();

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
                if (!$isDryRun) {
                    $mailbox->setIsDelete(true);
                    $this->trxMailboxRepository->setModel($mailbox);
                }

                $deletedCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("\nエラー: メールID {$mailbox->getId()} - {$e->getMessage()}");
            }

            $bar->advance();
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
     * 期限切れメールを取得
     *
     * @param int|null $playerId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getExpiredMailboxes(?int $playerId, int $limit): \Illuminate\Database\Eloquent\Collection
    {
        $query = \App\Models\Trx\TrxMailbox::query()
            ->where('is_delete', false)
            ->where('is_protected', false)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', Carbon::now())
            ->limit($limit);

        if ($playerId !== null) {
            $query->where('sys_player_id', $playerId);
        }

        return $query->get();
    }
}
