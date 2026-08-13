<?php

namespace NexusGacha\Strategies;

use NexusGacha\ValueObjects\GachaPrize;
use NexusGacha\Exceptions\GachaDrawException;

/**
 * NoneDrawStrategy
 * 
 * 通常抽選型のガチャ抽選戦略
 * 
 * selection_type='none'の場合に使用されます。
 * 通常のレアリティ抽選 → 景品抽選のフローを実行します。
 * ただし、bonus_rarityが指定されている場合はそのレアリティで確定します。
 * 
 * 使用例：
 * - "10連目はSSR確定"（bonus_rarity=5指定）
 * - "3連目はSR以上確定"（bonus_rarity=4指定）
 */
class NoneDrawStrategy implements GachaDrawStrategyInterface
{
    /**
     * {@inheritDoc}
     */
    public function supports(string $selectionType): bool
    {
        return $selectionType === 'none';
    }
    
    /**
     * {@inheritDoc}
     * 
     * @throws GachaDrawException
     *   - CODE_NO_RARITY_RATES: レアリティ確率データが見つからない
     *   - CODE_NO_PRIZES: 景品データが見つからない
     */
    public function draw(
        mixed $bonus,
        ?string $selectedCandidateId,
        string $mstGachaId,
        GachaDrawContext $context
    ): GachaPrize {
        $bonusRarity = $bonus->getAttribute('bonus_rarity');
        $isPickupOnly = $bonus->getAttribute('is_pickup_only');
        
        // 1. レアリティが指定されている場合は確定抽選
        if ($bonusRarity) {
            return $this->drawPrize($mstGachaId, $bonusRarity, $isPickupOnly, true, $context);
        }

        // 2. レアリティ未指定の場合は通常抽選
        return $this->drawNormal($mstGachaId, $context);
    }
    
    /**
     * 通常抽選（レアリティ抽選 → 景品抽選）
     * 
     * @param string $mstGachaId ガチャID
     * @param GachaDrawContext $context コンテキスト
     * @return GachaPrize 景品情報
     * @throws GachaDrawException
     */
    private function drawNormal(string $mstGachaId, GachaDrawContext $context): GachaPrize
    {
        // 1. レアリティ抽選
        $rarity = $this->drawRarity($mstGachaId, $context);
        
        // 2. 景品抽選
        return $this->drawPrize($mstGachaId, $rarity, false, false, $context);
    }
    
    /**
     * レアリティを抽選
     * 
     * @param string $mstGachaId ガチャID
     * @param GachaDrawContext $context コンテキスト
     * @return int レアリティ（1～5）
     * @throws GachaDrawException レアリティ確率データが見つからない場合
     */
    private function drawRarity(string $mstGachaId, GachaDrawContext $context): int
    {
        $rarityRates = $context->rarityRateRepository->selectByGachaId($mstGachaId);
        
        if ($rarityRates->isEmpty()) {
            throw new GachaDrawException(
                "No rarity rates found for gacha: {$mstGachaId}",
                GachaDrawException::CODE_NO_RARITY_RATES
            );
        }
        
        // 総確率を計算
        $totalRate = $rarityRates->sum('rate');
        $rand = rand(1, $totalRate);
        
        // 累積確率で抽選
        $accumulated = 0;
        foreach ($rarityRates as $rarityRate) {
            $accumulated += $rarityRate->getAttribute('rate');
            if ($rand <= $accumulated) {
                return $rarityRate->getAttribute('rarity');
            }
        }

        // フォールバック（レアリティ1）
        return 1;
    }
    
    /**
     * 景品を抽選
     * 
     * @param string $mstGachaId ガチャID
     * @param int $rarity レアリティ
     * @param bool $pickupOnly ピックアップのみ抽選するか
     * @param bool $isGuaranteed 確定抽選か
     * @param GachaDrawContext $context コンテキスト
     * @return GachaPrize 景品情報
     * @throws GachaDrawException 景品データが見つからない場合
     */
    private function drawPrize(
        string $mstGachaId,
        int $rarity,
        bool $pickupOnly,
        bool $isGuaranteed,
        GachaDrawContext $context
    ): GachaPrize {
        $prizes = $context->prizeRepository->selectByGachaIdAndRarity($mstGachaId, $rarity, $pickupOnly);
        
        // ピックアップのみで景品がない場合は通常景品から
        if ($prizes->isEmpty() && $pickupOnly) {
            $prizes = $context->prizeRepository->selectByGachaIdAndRarity($mstGachaId, $rarity, false);
        }
        
        if ($prizes->isEmpty()) {
            throw new GachaDrawException(
                "No prizes available for selection",
                GachaDrawException::CODE_NO_PRIZES
            );
        }

        // 重み付きランダム抽選
        $prize = $this->weightedRandom($prizes->all(), 'weight');

        return new GachaPrize(
            contentType: $prize->getAttribute('content_type'),
            contentId: $prize->getAttribute('content_id'),
            amount: $prize->getAttribute('amount'),
            rarity: $rarity,
            isGuaranteed: $isGuaranteed
        );
    }
    
    /**
     * 重み付きランダム抽選
     * 
     * @param array $items 候補アイテム配列
     * @param string $weightKey 重みを取得するための属性キー
     * @return mixed 抽選されたアイテム
     * @throws GachaDrawException 候補が空の場合
     */
    private function weightedRandom(array $items, string $weightKey): mixed
    {
        if (empty($items)) {
            throw new GachaDrawException(
                'No items available for weighted random selection',
                GachaDrawException::CODE_EMPTY_ITEMS
            );
        }

        $totalWeight = array_sum(array_map(fn($item) => $item->getAttribute($weightKey), $items));
        $rand = rand(1, $totalWeight);
        
        $accumulated = 0;
        foreach ($items as $item) {
            $accumulated += $item->getAttribute($weightKey);
            if ($rand <= $accumulated) {
                return $item;
            }
        }

        return $items[0];
    }
}
