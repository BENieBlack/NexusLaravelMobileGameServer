<?php

namespace App\Models\Trx;

use App\Persistence\ApiSession;
use Nexus\Core\Models\Trx\_BaseTrx as PersistenceBaseTrx;
use NexusTidb\Traits\UuidPrimaryKey;

/**
 * _BaseTrx
 *
 * Trxデータベースのモデル基底クラス
 * Unit of Workパターンで管理されるトランザクションデータ
 */
abstract class _BaseTrx extends PersistenceBaseTrx implements _BaseTrxInterface
{
    // TiDB利用時のみ、単一主キーidをUUIDで払い出す
    use UuidPrimaryKey;

    /**
     * ログイン中プレイヤーの割り当てシャードを返す
     *
     * プレイヤーが居ない文脈（コンソール・バッチ等）だけ null を返し、
     * 基底クラスの既定接続に委ねる。
     *
     * ログイン中プレイヤーに割り当てが無い場合は例外をそのまま投げる。
     * サインアップがプレイヤー作成の直後に割り当てを作るため、
     * リクエスト経路でこの状態は起きない。ここで既定接続へ退避すると、
     * 書き込みと読み出しが別のDBに向いて「行が消えた」ように見えるだけで、
     * 原因が分からなくなる。
     */
    protected static function resolveShardConnection(): ?string
    {
        if (! ApiSession::hasSysPlayerId()) {
            return null;
        }

        return ApiSession::resolveConnectionName('trx');
    }
}
