<?php

namespace App\Domain\Sharding\Services;

use App\Models\Sys\SysSharding;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;

/**
 * ShardAssignmentService
 *
 * プレイヤーをTrxDBのシャードへ割り当てる
 *
 * trx_* の読み書き先は sys_sharding_node_player の割り当てで決まる。
 * 割り当てが無いプレイヤーは接続先を解決できずエラーになるため、
 * プレイヤー作成時に必ず1件作る。
 *
 * 割り当ては一度決めたら動かさない。sys_sharding.strategy は
 * 「初回にどのノードを選ぶか」だけに使う。
 */
class ShardAssignmentService
{
    /**
     * シャーディング定義の名前（trx_* 用）
     */
    private const SHARDING_NAME = 'trx_sharding';

    /**
     * プレイヤーをシャードへ割り当て、ノード番号を返す
     *
     * 既に割り当て済みならそのノード番号を返す（再割り当てはしない）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @return int ノード番号（1始まり。trx1, trx2, ... に対応）
     *
     * @throws \RuntimeException 割り当て可能なノードが無い場合
     */
    public function assign(int $sysPlayerId): int
    {
        $assignedNodeNo = $this->findAssignedNodeNo($sysPlayerId);

        if ($assignedNodeNo !== null) {
            return $assignedNodeNo;
        }

        $node = $this->chooseNode($sysPlayerId);

        $this->insertAssignment($sysPlayerId, (int) $node->id);

        return (int) $node->node_no;
    }

    /**
     * ノードを指定して割り当てる
     *
     * 既存プレイヤーの移行など、選び方を呼び出し側が決める場合に使う。
     * 既に割り当て済みなら何もしない（割り当ては動かさない）。
     *
     * @param  int  $nodeNo  ノード番号（1始まり）
     * @return bool 今回割り当てたならtrue
     *
     * @throws \RuntimeException 指定したノードが無い、または書き込み不可の場合
     */
    public function assignToNode(int $sysPlayerId, int $nodeNo): bool
    {
        if ($this->findAssignedNodeNo($sysPlayerId) !== null) {
            return false;
        }

        $node = DB::connection('sys')->table('sys_sharding_node as n')
            ->join('sys_sharding as s', 's.id', '=', 'n.sys_sharding_id')
            ->where('s.name', self::SHARDING_NAME)
            ->where('n.node_no', $nodeNo)
            ->where('n.status', 'active')
            ->where('n.is_writable', true)
            ->select('n.id')
            ->first();

        if ($node === null) {
            throw new \RuntimeException("Writable sharding node not found: node_no={$nodeNo}");
        }

        $this->insertAssignment($sysPlayerId, (int) $node->id);

        return true;
    }

    /**
     * 割り当て済みのノード番号を返す（未割り当てならnull）
     */
    public function findAssignedNodeNo(int $sysPlayerId): ?int
    {
        $row = DB::connection('sys')->table('sys_sharding_node_player as p')
            ->join('sys_sharding_node as n', 'n.id', '=', 'p.sys_sharding_node_id')
            ->where('p.sys_player_id', $sysPlayerId)
            ->value('n.node_no');

        return $row === null ? null : (int) $row;
    }

    /**
     * 割り当てを1件作り、ノードの人数を増やす
     */
    private function insertAssignment(int $sysPlayerId, int $shardingNodeId): void
    {
        $now = ClockUtility::nowToString();

        DB::connection('sys')->table('sys_sharding_node_player')->insert([
            'sys_sharding_node_id' => $shardingNodeId,
            'sys_player_id' => $sysPlayerId,
            'assigned_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::connection('sys')->table('sys_sharding_node')
            ->where('id', $shardingNodeId)
            ->increment('current_player_count');
    }

    /**
     * 割り当て先のノードを選ぶ
     *
     * @return object ノード行（id, node_no を持つ）
     *
     * @throws \RuntimeException 書き込み可能なアクティブノードが無い場合
     */
    private function chooseNode(int $sysPlayerId): object
    {
        $sharding = DB::connection('sys')->table('sys_sharding')
            ->where('name', self::SHARDING_NAME)
            ->where('is_active', true)
            ->first();

        if ($sharding === null) {
            throw new \RuntimeException(
                'Active sharding definition not found: '.self::SHARDING_NAME
            );
        }

        /** @var list<object> $nodes */
        $nodes = DB::connection('sys')->table('sys_sharding_node')
            ->where('sys_sharding_id', $sharding->id)
            ->where('status', 'active')
            ->where('is_writable', true)
            ->orderBy('node_no')
            ->get()
            ->all();

        // 接続設定が無いノードへ割り当てると、以降その行を読み書きできなくなる。
        // sys_sharding_node の行数と DB_SHARD_COUNT がずれていても安全side に倒す
        /** @var list<string> $available */
        $available = config('database.pitr.active_trx_connections', ['trx1']);
        $nodes = array_values(array_filter(
            $nodes,
            fn (object $node) => in_array('trx'.$node->node_no, $available, true)
        ));

        if ($nodes === []) {
            throw new \RuntimeException(
                'No writable sharding node available for: '.self::SHARDING_NAME
            );
        }

        return match ($sharding->strategy) {
            // hash: プレイヤーIDを割ってノードを決める。均等に散らばる
            SysSharding::STRATEGY_HASH => $nodes[$sysPlayerId % count($nodes)],
            // それ以外の戦略は未実装なので、人数の少ないノードへ寄せる
            default => $this->chooseLeastLoadedNode($nodes),
        };
    }

    /**
     * 割り当て人数が最も少ないノードを返す
     *
     * @param  list<object>  $nodes
     */
    private function chooseLeastLoadedNode(array $nodes): object
    {
        usort(
            $nodes,
            fn (object $a, object $b) => [$a->current_player_count, $a->node_no]
                <=> [$b->current_player_count, $b->node_no]
        );

        return $nodes[0];
    }
}
