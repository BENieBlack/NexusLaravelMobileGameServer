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
     * @return Collection<string, TrxPlayerSns>
     */
    public function findAll(): Collection
    {
        $sysPlayerId = $this->getSysPlayerId();
        return $this->getMapBySysPlayerId($sysPlayerId);
    }

    /**
     * プレイヤーIDとSNSタイプで連携情報を取得
     *
     * @param string $snsType SNSタイプ (apple, google, x, facebook)
     * @return TrxPlayerSns|null
     */
    public function findBySnsType(string $snsType): ?TrxPlayerSns
    {
        $sysPlayerId = $this->getSysPlayerId();
        
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
    public function findBySnsTypeAndSnsUserId(string $snsType, string $snsUserId): ?TrxPlayerSns
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
     * @param string $snsType SNSタイプ
     * @return bool
     */
    public function existsBySnsType(string $snsType): bool
    {
        return $this->findBySnsType($snsType) !== null;
    }

    /**
     * 有効な連携数を取得
     *
     * @return int
     */
    public function countActive(): int
    {
        $sysPlayerId = $this->getSysPlayerId();
        
        return $this->getMapBySysPlayerId($sysPlayerId)
            ->where('is_delete', false)
            ->count();
    }
}
