<?php

namespace App\Domain\InAppPurchase\Services;

use App\Models\Mst\MstInAppPurchase;
use App\Models\Trx\TrxInAppPurchaseEffect;
use App\Repositories\Trx\TrxInAppPurchaseEffectRepository;
use App\Persistence\ApiSession;
use App\Utilities\Clock;
use Illuminate\Support\Collection;

/**
 * PassService
 *
 * Pass商品の効果管理を担当するサービス
 */
class PassService
{
    public function __construct(
        private readonly TrxInAppPurchaseEffectRepository $trxInAppPurchaseEffectRepository,
        private readonly ApiSession $apiSession,
    ) {
    }

    /**
     * Pass購入時に効果を適用
     *
     * @param int $sysPlayerId プレイヤーID
     * @param MstInAppPurchase $mstInAppPurchase Pass商品マスター
     * @return void
     */
    public function applyPassEffects(int $sysPlayerId, MstInAppPurchase $mstInAppPurchase): void
    {
        
        // 商品の効果を取得
        $effectCollection = $mstInAppPurchase->effects;

        if ($effectCollection->isEmpty()) {
            return;
        }

        // 有効期限を計算
        $expiresAt = Clock::now()->addDays($mstInAppPurchase->getEffectDurationDays() ?? 30);

        // 各効果を適用（同じ効果を複数回購入可能）
        foreach ($effectCollection as $mstEffect) {
            // 常に新規効果を作成（複数購入による効果累積を許可）
            $newEffect = new TrxInAppPurchaseEffect([
                'sys_player_id' => $sysPlayerId,
                'mst_in_app_purchase_id' => $mstInAppPurchase->getId(),
                'effect_type' => $mstEffect->getEffectType(),
                'value' => $mstEffect->getValue(),
                'expires_at' => $expiresAt,
                'is_active' => true,
            ]);
            $this->trxInAppPurchaseEffectRepository->setModel($newEffect);
        }
    }

    /**
     * プレイヤーの有効なPass効果を取得
     * 有効期限切れの効果にis_deleteフラグを立てる
     *
     * @param int $sysPlayerId プレイヤーID
     * @return Collection<int, TrxInAppPurchaseEffect>
     */
    public function getActiveEffects(int $sysPlayerId): Collection
    {
        
        // 全ての効果を取得
        $effects = $this->trxInAppPurchaseEffectRepository->getMapBySysPlayerId($sysPlayerId);
        
        $activeEffects = collect();

        // 有効な効果のみをフィルタし、無効な効果にis_deleteフラグを立てる
        foreach ($effects as $effect) {
            if ($effect->isEffective()) {
                $activeEffects->push($effect);
            } else {
                // 有効期限切れまたは無効な効果にis_deleteフラグを立てる
                $effect->setIsDelete(true);
                $this->trxInAppPurchaseEffectRepository->setModel($effect);
            }
        }

        return $activeEffects;
    }

    /**
     * プレイヤーの特定効果タイプの効果値の合計を取得
     *
     * @param int $sysPlayerId プレイヤーID
     * @param string $effectType 効果タイプ
     * @return float 効果値の合計
     */
    public function getTotalEffectValue(int $sysPlayerId, string $effectType): float
    {
        return $this->getActiveEffects($sysPlayerId)
            ->where('effect_type', $effectType)
            ->sum('value');
    }

    /**
     * Pass効果を無効化
     *
     * @param int $mstInAppPurchaseId 商品ID
     * @return void
     */
    public function deactivatePassEffects(int $mstInAppPurchaseId): void
    {
        $effects = $this->trxInAppPurchaseEffectRepository->selectAllEffectsByMstInAppPurchaseId($mstInAppPurchaseId);

        foreach ($effects as $effect) {
            if ($effect->getIsActive()) {
                $effect->setIsActive(false);
                $this->trxInAppPurchaseEffectRepository->setModel($effect);
            }
        }
    }
}
