<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxPlayerSns;
use Nexus\Core\Support\CustomCollection;

/**
 * TrxPlayerSnsRepository
 *
 * プレイヤーのSNS連携情報のRepository
 * 複合主キー (sys_player_id, sns_type) を持つテーブル
 *
 * @extends _BaseTrxRepository<TrxPlayerSns>
 */
class TrxPlayerSnsRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxPlayerSns::class;

    /**
     * プレイヤーIDでSNS連携情報を全て取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @return CustomCollection<string, TrxPlayerSns>
     */
    public function selectAll(int $sysPlayerId): CustomCollection
    {
        return $this->selectMapBySysPlayerId($sysPlayerId);
    }

    /**
     * プレイヤーIDとSNSタイプで連携情報を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $snsType  SNSタイプ (apple, google, x, facebook)
     */
    public function selectBySnsType(int $sysPlayerId, string $snsType): ?TrxPlayerSns
    {

        return $this->selectMapBySysPlayerId($sysPlayerId)
            ->where('sns_type', $snsType)
            ->where('is_delete', false)
            ->first();
    }

    /**
     * SNSユーザーIDで連携情報を取得（全プレイヤー対象）
     * 認証時に使用
     *
     * @param  string  $snsType  SNSタイプ
     * @param  string  $snsUserId  SNSユーザーID
     */
    public function selectBySnsTypeAndSnsUserId(string $snsType, string $snsUserId): ?TrxPlayerSns
    {
        return TrxPlayerSns::query()
            ->where('sns_type', $snsType)
            ->where('sns_user_id', $snsUserId)
            ->where('is_delete', false)
            ->first();
    }

    /**
     * 連携が存在するかチェック
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $snsType  SNSタイプ
     */
    public function existsBySnsType(int $sysPlayerId, string $snsType): bool
    {
        return $this->selectBySnsType($sysPlayerId, $snsType) !== null;
    }

    /**
     * 有効な連携数を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     */
    public function countActive(int $sysPlayerId): int
    {
        return $this->selectMapBySysPlayerId($sysPlayerId)
            ->where('is_delete', false)
            ->count();
    }
}
