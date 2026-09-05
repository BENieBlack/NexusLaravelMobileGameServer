<?php

namespace App\Models\Log;

use App\Persistence\ApiSession;
use Nexus\Core\Models\Log\_BaseLog as PersistenceBaseLog;
use NexusPitr\Logger\ShardMapper;
use NexusTidb\Traits\UuidPrimaryKey;

/**
 * _BaseLog
 *
 * Logデータベースのモデル基底クラス
 * Unit of Workパターンで管理されるログデータ
 */
abstract class _BaseLog extends PersistenceBaseLog implements _BaseLogInterface
{
    // TiDB利用時のみ、単一主キーidをUUIDで払い出す
    use UuidPrimaryKey;

    /**
     * ログイン中プレイヤーの割り当てシャードに対応するLogDB接続を返す
     *
     * ログは対になるTrxシャードと同じ番号のLogDBへ書く（trx2 → log2）
     */
    protected static function resolveShardConnection(): ?string
    {
        if (! ApiSession::hasSysPlayerId()) {
            return null;
        }

        return ShardMapper::resolveLogConnection(ApiSession::resolveConnectionName('trx'));
    }
}
