<?php

namespace NexusGacha\Strategies;

use NexusGacha\DataTransferObjects\PrizeCandidate;
use NexusGacha\DataTransferObjects\RarityRate;
use NexusGacha\DataTransferObjects\StepBonus;
use NexusGacha\Exceptions\GachaDrawException;
use NexusGacha\ValueObjects\GachaPrize;

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
     *                            - CODE_NO_RARITY_RATES: レアリティ確率データが見つからない
     *                            - CODE_NO_PRIZES: 景品データが見つからない
     */
    public function draw(
        StepBonus $bonus,
        ?string $selectedCandidateId,
        string $mstGachaId,
        GachaDrawContext $context
    ): GachaPrize {
        $bonusRarity = $bonus->getBonusRarity();
        $isPickupOnly = $bonus->isPickupOnly();

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
     * @param  string  $mstGachaId  ガチャID
     * @param  GachaDrawContext  $context  コンテキスト
     * @return GachaPrize 景品情報
     *
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
     * @param  string  $mstGachaId  ガチャID
     * @param  GachaDrawContext  $context  コンテキスト
     * @return int レアリティ（1～5）
     *
     * @throws GachaDrawException レアリティ確率データが見つからない場合
     */
    private function drawRarity(string $mstGachaId, GachaDrawContext $context): int
    {
        $rarityRates = $context->rarityRateRepository->selectByGachaId($mstGachaId);

        if ($rarityRates === []) {
            throw new GachaDrawException(
                "No rarity rates found for gacha: {$mstGachaId}",
                GachaDrawException::CODE_NO_RARITY_RATES
            );
        }

        // 総確率を計算
        $totalRate = array_sum(array_map(fn (RarityRate $rarityRate) => $rarityRate->getRate(), $rarityRates));
        $rand = rand(1, $totalRate);

        // 累積確率で抽選
        $accumulated = 0;
        foreach ($rarityRates as $rarityRate) {
            $accumulated += $rarityRate->getRate();
            if ($rand <= $accumulated) {
                return $rarityRate->getRarity();
            }
        }

        // フォールバック（レアリティ1）
        return 1;
    }

    /**
     * 景品を抽選
     *
     * @param  string  $mstGachaId  ガチャID
     * @param  int  $rarity  レアリティ
     * @param  bool  $pickupOnly  ピックアップのみ抽選するか
     * @param  bool  $isGuaranteed  確定抽選か
     * @param  GachaDrawContext  $context  コンテキスト
     * @return GachaPrize 景品情報
     *
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
        if ($prizes === [] && $pickupOnly) {
            $prizes = $context->prizeRepository->selectByGachaIdAndRarity($mstGachaId, $rarity, false);
        }

        if ($prizes === []) {
            throw new GachaDrawException(
                'No prizes available for selection',
                GachaDrawException::CODE_NO_PRIZES
            );
        }

        // 重み付きランダム抽選
        $prize = $this->weightedRandom($prizes);

        return new GachaPrize(
            contentType: $prize->getContentType(),
            contentMstId: $prize->getContentMstId(),
            contentOption: $prize->getContentOption(),
            amount: $prize->getAmount(),
            rarity: $rarity,
            isGuaranteed: $isGuaranteed
        );
    }

    /**
     * 重み付きランダム抽選
     *
     * @param  list<PrizeCandidate>  $items  候補アイテム配列
     * @return PrizeCandidate 抽選されたアイテム
     *
     * @throws GachaDrawException 候補が空の場合
     */
    private function weightedRandom(array $items): PrizeCandidate
    {
        if (empty($items)) {
            throw new GachaDrawException(
                'No items available for weighted random selection',
                GachaDrawException::CODE_EMPTY_ITEMS
            );
        }

        $totalWeight = array_sum(array_map(fn (PrizeCandidate $item) => $item->getWeight(), $items));
        $rand = rand(1, $totalWeight);

        $accumulated = 0;
        foreach ($items as $item) {
            $accumulated += $item->getWeight();
            if ($rand <= $accumulated) {
                return $item;
            }
        }

        return $items[0];
    }
}
