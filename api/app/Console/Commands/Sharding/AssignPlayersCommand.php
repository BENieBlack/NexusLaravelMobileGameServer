<?php

namespace App\Console\Commands\Sharding;

use App\Domain\Sharding\Services\PlayerShardLocatorService;
use App\Domain\Sharding\Services\ShardAssignmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * AssignPlayersCommand
 *
 * 割り当ての無いプレイヤーをTrxDBシャードへ割り当てる
 *
 * 割り当てはサインアップ時に作られるため、その仕組みが入る前に
 * 作られたプレイヤーには割り当てが無い。割り当てが無いと接続先を
 * 解決できず、ログインが失敗する。
 *
 * 既存データがあるシャードへ寄せるのが要点。ハッシュで振り直すと
 * 今あるデータへ届かなくなる。
 */
class AssignPlayersCommand extends Command
{
    /**
     * コマンドのシグネチャ
     *
     * @var string
     */
    protected $signature = 'sharding:assign-players
                            {--dry-run : 実行結果をシミュレートのみ（実際には割り当てない）}
                            {--player-id= : 特定プレイヤーのみ処理}
                            {--limit=1000 : 一度に処理する最大人数}';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = '割り当ての無いプレイヤーをTrxDBシャードへ割り当てる';

    public function __construct(
        private readonly ShardAssignmentService $shardAssignmentService,
        private readonly PlayerShardLocatorService $playerShardLocator,
    ) {
        parent::__construct();
    }

    /**
     * コマンド実行
     */
    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $playerId = $this->option('player-id');
        $limit = (int) $this->option('limit');

        $this->info('シャード割り当てのバックフィルを開始します');

        if ($isDryRun) {
            $this->warn('[DRY RUN モード] 実際には割り当てません');
        }

        $sysPlayerIds = $this->findUnassignedPlayerIds(
            $playerId === null ? null : (int) $playerId,
            $limit,
        );

        $totalCount = count($sysPlayerIds);

        if ($totalCount === 0) {
            $this->info('割り当てが必要なプレイヤーはいませんでした');

            return Command::SUCCESS;
        }

        $this->info("割り当てが必要なプレイヤーが {$totalCount} 人見つかりました");

        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $assignedCountByNode = [];
        $keptWithDataCount = 0;
        $errorCount = 0;

        foreach ($sysPlayerIds as $sysPlayerId) {
            try {
                // 既にデータがあるシャードがあれば、そこへ寄せる
                $nodeNoWithData = $this->playerShardLocator->findNodeNoHoldingData($sysPlayerId);

                if ($isDryRun) {
                    $nodeNo = $nodeNoWithData ?? 0;
                } elseif ($nodeNoWithData !== null) {
                    $this->shardAssignmentService->assignToNode($sysPlayerId, $nodeNoWithData);
                    $nodeNo = $nodeNoWithData;
                } else {
                    // データが無ければ通常の選び方に任せる
                    $nodeNo = $this->shardAssignmentService->assign($sysPlayerId);
                }

                if ($nodeNoWithData !== null) {
                    $keptWithDataCount++;
                }

                $assignedCountByNode[$nodeNo] = ($assignedCountByNode[$nodeNo] ?? 0) + 1;
            } catch (\Exception $e) {
                $errorCount++;
                $this->error("\nエラー: プレイヤーID {$sysPlayerId} - {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('=== 処理結果 ===');
        $this->info('割り当て: '.array_sum($assignedCountByNode).' 人');
        $this->info("うち既存データのあるシャードへ寄せた: {$keptWithDataCount} 人");

        ksort($assignedCountByNode);

        foreach ($assignedCountByNode as $nodeNo => $count) {
            $label = $nodeNo === 0 ? '（データ無し・DRY RUNのため未確定）' : "trx{$nodeNo}";
            $this->line("  {$label}: {$count} 人");
        }

        if ($errorCount > 0) {
            $this->error("エラー: {$errorCount} 件");
        }

        if ($isDryRun) {
            $this->warn('[DRY RUN モード] 実際には割り当てていません');
        }

        $this->newLine();
        $this->info('完了');

        return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * 割り当てが無いプレイヤーのIDを返す
     *
     * @return list<int>
     */
    private function findUnassignedPlayerIds(?int $playerId, int $limit): array
    {
        $query = DB::connection('sys')->table('sys_player as p')
            ->leftJoin('sys_sharding_node_player as a', 'a.sys_player_id', '=', 'p.id')
            ->whereNull('a.sys_player_id')
            ->orderBy('p.id')
            ->limit($limit)
            ->select('p.id');

        if ($playerId !== null) {
            $query->where('p.id', $playerId);
        }

        return $query->pluck('p.id')->map(fn ($id) => (int) $id)->all();
    }
}
