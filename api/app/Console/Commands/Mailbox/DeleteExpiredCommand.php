<?php

namespace App\Console\Commands\Mailbox;

use App\Models\Trx\TrxMailbox;
use App\Repositories\Trx\TrxMailboxRepository;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Nexus\Core\UnitOfWork\Contracts\QueryManagerInterface;
use Nexus\Core\Utilities\ClockUtility;

/**
 * DeleteExpiredCommand
 *
 * 期限切れメールを自動削除するバッチコマンド
 *
 * trx_mailbox はプレイヤーごとにシャードへ分かれているため、
 * TrxMailbox の既定接続（trx1）だけを見ると他シャードのメールが消えない。
 * config('database.pitr.active_trx_connections') の全シャードを走査する。
 *
 * 候補の一覧はロックなしで読むが、削除は行ごとのトランザクションで
 * FOR UPDATE して読み直し、保護・削除済み・期限を再判定してから書く
 * （プレイヤーの保護の切り替えと競合しても、保護済みのメールを消さない）。
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
        $this->info('実行時刻: '.ClockUtility::now()->toDateTimeString());

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
            // （ロックもこの接続で取る）
            $this->trxMailboxRepository->setConnection($connection);

            foreach ($expiredMailboxes as $mailbox) {
                try {
                    // 上の一覧はロックなしの読み取り（候補の絞り込み）に過ぎない。
                    // 読んだ後にプレイヤーが保護した行を消さないよう、
                    // 実際の判定と書き込みは行ごとのトランザクションで FOR UPDATE して読み直してから行う。
                    // dry-run はロックしないため、削除予定の件数は目安（実行時に再判定で減りうる）
                    $deleted = $isDryRun
                        ? self::isDeletable($mailbox)
                        : $this->deleteIfStillDeletable($connection, $mailbox->getId());

                    if ($deleted) {
                        $deletedCount++;
                    } else {
                        $skippedCount++;
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("\nエラー: メールID {$mailbox->getId()} - {$e->getMessage()}");
                }

                $bar->advance();
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
     * 1 行をトランザクション内で FOR UPDATE して読み直し、まだ削除対象なら論理削除する
     *
     * trx_mailbox.is_protected は本人の API（Mailbox/LockUseCase）も書く。
     * バッチは player.lock の外で走るため、行ロックで直列化する。
     * 先に保護された行はロックが外れた後の最新の値で読むので、再判定で除外される。
     * 単一行しかロックしないので、LockUseCase とデッドロックしない。
     *
     * @param  string  $connection  対象行のシャード接続（setConnection() 済みであること）
     * @return bool 論理削除したら true、再判定で対象外だったら false
     */
    private function deleteIfStillDeletable(string $connection, int $mailboxId): bool
    {
        try {
            $result = DB::connection($connection)->transaction(function () use ($mailboxId): bool {
                $mailbox = $this->trxMailboxRepository->selectByIdForUpdate($mailboxId);

                // 消えた行、または再判定で対象外になった行（保護された・既に削除済み・期限が延びた）は触らない
                if ($mailbox === null || ! self::isDeletable($mailbox)) {
                    return false;
                }

                $mailbox->setIsDelete(true);
                $this->trxMailboxRepository->setModel($mailbox);

                // setModel() はキューに積むだけなので、ロックを持っている間に書き込む
                $this->queryManager->flush();

                return true;
            });

            // コミットした行の updated_resources を持ち越さない（バッチは API の応答に返さない）
            $this->queryManager->pullUpdatedResources();

            return $result;
        } catch (\Throwable $e) {
            // ロールバックした行の控えも捨てる
            $this->queryManager->discardUpdatedResources();
            // 失敗した行のキューを持ち越さない（次の行の flush に混ざらないように）
            $this->trxMailboxRepository->clearQueue();

            throw $e;
        } finally {
            $this->trxMailboxRepository->forgetCachedModels();
        }
    }

    /**
     * 削除対象かどうか（ロック読みした最新の行に対して呼ぶ）
     *
     * 保護されておらず、削除済みでなく、期限が設定されていて過ぎていること。
     */
    public static function isDeletable(TrxMailbox $mailbox): bool
    {
        if ($mailbox->getIsProtected() || $mailbox->getIsDelete()) {
            return false;
        }

        return $mailbox->getExpiresAt() !== null && $mailbox->isExpired();
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
                ->where('expires_at', '<=', ClockUtility::now())
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
