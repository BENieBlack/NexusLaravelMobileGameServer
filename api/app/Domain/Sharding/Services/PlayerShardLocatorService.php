<?php

namespace App\Domain\Sharding\Services;

use Illuminate\Support\Facades\DB;

/**
 * PlayerShardLocatorService
 *
 * プレイヤーの行が実際に入っているTrxDBシャードを探す
 *
 * 既存プレイヤーへ割り当てを作るときに使う。
 * 割り当てが無かった頃のデータはモデルの既定接続（trx1）に入っているため、
 * ハッシュで振り直すと今あるデータへ届かなくなる。
 */
class PlayerShardLocatorService
{
    /**
     * sys_player_id を持つテーブル名のキャッシュ（接続名 => テーブル名）
     *
     * @var array<string, list<string>>
     */
    private array $playerTablesByConnection = [];

    /**
     * プレイヤーの行があるシャードのノード番号を返す
     *
     * 複数のシャードに散っている場合は、行が見つかった最初のシャードを返す。
     * どこにも無ければ null（新規と同じ扱いでよい）。
     *
     * @return int|null ノード番号（1始まり）
     */
    public function findNodeNoHoldingData(int $sysPlayerId): ?int
    {
        foreach ($this->trxConnections() as $connection) {
            if ($this->hasPlayerData($connection, $sysPlayerId)) {
                return (int) substr($connection, 3);
            }
        }

        return null;
    }

    /**
     * 指定シャードにプレイヤーの行があるか
     */
    public function hasPlayerData(string $connection, int $sysPlayerId): bool
    {
        $tables = $this->findPlayerTables($connection);

        if ($tables === []) {
            return false;
        }

        // テーブルごとに問い合わせると往復が増えるため、1本のクエリにまとめる
        $selects = [];

        foreach ($tables as $table) {
            // UNIONで各節にLIMITを付けるには括弧が要る
            $selects[] = "(SELECT 1 FROM `{$table}` WHERE `sys_player_id` = ? LIMIT 1)";
        }

        $sql = implode(' UNION ALL ', $selects).' LIMIT 1';
        $bindings = array_fill(0, count($tables), $sysPlayerId);

        return DB::connection($connection)->select($sql, $bindings) !== [];
    }

    /**
     * sys_player_id を持つテーブルを返す
     *
     * @return list<string>
     */
    private function findPlayerTables(string $connection): array
    {
        if (isset($this->playerTablesByConnection[$connection])) {
            return $this->playerTablesByConnection[$connection];
        }

        $rows = DB::connection($connection)->select(
            'SELECT TABLE_NAME AS table_name FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = ?
             ORDER BY TABLE_NAME',
            ['sys_player_id']
        );

        return $this->playerTablesByConnection[$connection] = array_map(
            fn (object $row) => (string) $row->table_name,
            $rows
        );
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
