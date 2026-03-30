<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxPlayerSns;
use Illuminate\Support\Collection;

/**
 * TrxPlayerSnsRepository
 *
 * プレイヤーのSNS連携情報のRepository
 * 複合主キー (sys_player_id, sns_type) を持つテーブル
 */
class TrxPlayerSnsRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxPlayerSns::class;

    /**
     * プレイヤーIDでSNS連携情報を全て取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @return Collection<string, TrxPlayerSns>
     */
    public function selectAll(int $sysPlayerId): Collection
    {
        return $this->getMapBySysPlayerId($sysPlayerId);
    }

    /**
     * プレイヤーIDとSNSタイプで連携情報を取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $snsType SNSタイプ (apple, google, x, facebook)
     * @return TrxPlayerSns|null
     */
    public function selectBySnsType(int $sysPlayerId, string $snsType): ?TrxPlayerSns
    {
        
        return $this->getMapBySysPlayerId($sysPlayerId)
            ->where('sns_type', $snsType)
            ->where('is_delete', false)
            ->first();
    }

    /**
     * SNSユーザーIDで連携情報を取得（全プレイヤー対象）
     * 認証時に使用
     *
     * @param string $snsType SNSタイプ
     * @param string $snsUserId SNSユーザーID
     * @return TrxPlayerSns|null
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
     * @param int $sysPlayerId プレイヤーID
     * @param string $snsType SNSタイプ
     * @return bool
     */
    public function existsBySnsType(int $sysPlayerId, string $snsType): bool
    {
        return $this->selectBySnsType($sysPlayerId, $snsType) !== null;
    }

    /**
     * 有効な連携数を取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @return int
     */
    public function countActive(int $sysPlayerId): int
    {
        return $this->getMapBySysPlayerId($sysPlayerId)
            ->where('is_delete', false)
            ->count();
    }
}
