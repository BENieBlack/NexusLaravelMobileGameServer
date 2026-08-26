<?php

namespace App\Models\Trx;

use App\Persistence\ApiSession;
use Nexus\Core\Models\Trx\_BaseTrx as PersistenceBaseTrx;

/**
 * _BaseTrx
 *
 * Trxデータベースのモデル基底クラス
 * Unit of Workパターンで管理されるトランザクションデータ
 */
abstract class _BaseTrx extends PersistenceBaseTrx implements _BaseTrxInterface
{
    /**
     * ログイン中プレイヤーの割り当てシャードを返す
     *
     * プレイヤーが居ない文脈（コンソール等）や、割り当てを引けない場合はnullを返し、
     * 基底クラスの $fallbackConnection に委ねる。
     */
    protected static function resolveShardConnection(): ?string
    {
        if (! ApiSession::hasSysPlayerId()) {
            return null;
        }

        try {
            return ApiSession::resolveConnectionName('trx');
        } catch (\RuntimeException) {
            // 割り当てがまだ無いプレイヤー（作成直後など）は退避先で扱う
            return null;
        }
    }
}
