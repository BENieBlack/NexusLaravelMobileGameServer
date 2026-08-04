<?php

namespace NexusGacha\Services;

use NexusGacha\Dto\GachaPrizeDto;
use NexusGacha\Repositories\GachaRarityRateRepositoryInterface;
use NexusGacha\Repositories\GachaPrizeRepositoryInterface;
use NexusGacha\Repositories\GachaStepRepositoryInterface;
use NexusGacha\Repositories\GachaStepBonusRepositoryInterface;
use NexusGacha\Repositories\GachaStepBonusContentRepositoryInterface;

/**
 * GachaDrawService
 * 
 * ガチャの抽選ロジックを担当するサービス
 */
class GachaDrawService
{
    public function __construct(
        private readonly GachaRarityRateRepositoryInterface $rarityRateRepository,
        private readonly GachaPrizeRepositoryInterface $prizeRepository,
        private readonly GachaStepRepositoryInterface $stepRepository,
        private readonly GachaStepBonusRepositoryInterface $stepBonusRepository,
        private readonly GachaStepBonusContentRepositoryInterface $stepBonusContentRepository,
    ) {
    }

    /**
     * ガチャを実行して景品リストを取得
     *
     * @param string $mstGachaId
     * @param int $drawCount
     * @param bool $hasStepUp
     * @param int $currentStep
     * @param string|null $selectedCandidateId
     * @return array<GachaPrizeDto>
     */
    public function draw(
        string $mstGachaId,
        int $drawCount,
        bool $hasStepUp,
        int $currentStep,
        ?string $selectedCandidateId = null
    ): array {
        $prizes = [];

        // ステップアップガチャの場合、ステップ情報を取得
        $stepBonusList = [];
        if ($hasStepUp) {
            $step = $this->stepRepository->findByGachaIdAndNumber($mstGachaId, $currentStep);
            if ($step) {
                // ステップのボーナス景品リストを取得
                $stepBonusList = $this->stepBonusRepository
                    ->findByStepId($step->getAttribute('id'))
                    ->all();
            }
        }

        // 通常抽選とステップボーナスを組み合わせて実行
        for ($i = 0; $i < $drawCount; $i++) {
            $position = $i + 1;
            
            // この位置にボーナス景品があるかチェック
            $bonus = collect($stepBonusList)->firstWhere('position', $position);
            
            if ($bonus) {
                // ボーナス景品を抽選
                $prize = $this->drawBonus($bonus, $selectedCandidateId, $mstGachaId);
            } else {
                // 通常抽選
                $prize = $this->drawNormal($mstGachaId);
            }
            
            $prizes[] = $prize;
        }

        // position=0（ランダム位置）のボーナス景品を処理
        $randomBonusList = collect($stepBonusList)->where('position', 0)->values();
        foreach ($randomBonusList as $bonus) {
            for ($i = 0; $i < $bonus->getAttribute('bonus_count'); $i++) {
                $prize = $this->drawBonus($bonus, $selectedCandidateId, $mstGachaId);
                // ランダムな位置に挿入
                $randomPosition = rand(0, count($prizes) - 1);
                $prizes[$randomPosition] = $prize;
            }
        }

        return $prizes;
    }

    /**
     * 通常抽選
     *
     * @param string $mstGachaId
     * @return GachaPrizeDto
     */
    private function drawNormal(string $mstGachaId): GachaPrizeDto
    {
        // 1. レアリティ抽選
        $rarity = $this->drawRarity($mstGachaId);
        
        // 2. 景品抽選
        return $this->drawPrize($mstGachaId, $rarity, false);
    }

    /**
     * ボーナス景品抽選
     *
     * @param mixed $bonus
     * @param string|null $selectedCandidateId
     * @param string $mstGachaId
     * @return GachaPrizeDto
     * @throws \Exception
     */
    private function drawBonus($bonus, ?string $selectedCandidateId, string $mstGachaId): GachaPrizeDto
    {
        $selectionType = $bonus->getAttribute('selection_type');
        $bonusRarity = $bonus->getAttribute('bonus_rarity');
        $isPickupOnly = $bonus->getAttribute('is_pickup_only');

        if ($selectionType === 'choice') {
            // ユーザー選択
            if (!$selectedCandidateId) {
                throw new \Exception("Selected candidate ID is required for choice type");
            }

            $candidate = $this->stepBonusContentRepository->findById($selectedCandidateId);
            if (!$candidate || $candidate->getAttribute('mst_gacha_step_bonus_id') !== $bonus->getAttribute('id')) {
                throw new \Exception("Invalid candidate ID");
            }

            return new GachaPrizeDto(
                contentType: $candidate->getAttribute('content_type'),
                contentId: $candidate->getAttribute('content_id'),
                amount: $candidate->getAttribute('amount'),
                rarity: $bonusRarity,
                isGuaranteed: true
            );
        } elseif ($selectionType === 'random') {
            // 候補からランダム
            $candidates = $this->stepBonusContentRepository->findByBonusId($bonus->getAttribute('id'));
            
            if ($candidates->isEmpty()) {
                throw new \Exception("No candidates found for random selection");
            }

            $candidate = $this->weightedRandom($candidates->all(), 'weight');

            return new GachaPrizeDto(
                contentType: $candidate->getAttribute('content_type'),
                contentId: $candidate->getAttribute('content_id'),
                amount: $candidate->getAttribute('amount'),
                rarity: $bonusRarity,
                isGuaranteed: true
            );
        } else {
            // none: 通常抽選だが確定レアリティ
            if ($bonusRarity) {
                return $this->drawPrize($mstGachaId, $bonusRarity, $isPickupOnly);
            }

            return $this->drawNormal($mstGachaId);
        }
    }

    /**
     * レアリティを抽選
     *
     * @param string $mstGachaId
     * @return int
     */
    private function drawRarity(string $mstGachaId): int
    {
        $rarityRates = $this->rarityRateRepository->findByGachaId($mstGachaId);
        
        $totalRate = $rarityRates->sum('rate');
        $rand = rand(1, $totalRate);
        
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
     * @param string $mstGachaId
     * @param int $rarity
     * @param bool $pickupOnly
     * @return GachaPrizeDto
     */
    private function drawPrize(string $mstGachaId, int $rarity, bool $pickupOnly): GachaPrizeDto
    {
        $prizes = $this->prizeRepository->findByGachaIdAndRarity($mstGachaId, $rarity, $pickupOnly);
        
        if ($prizes->isEmpty()) {
            // ピックアップのみで景品がない場合は通常景品から
            if ($pickupOnly) {
                $prizes = $this->prizeRepository->findByGachaIdAndRarity($mstGachaId, $rarity, false);
            }
        }

        $prize = $this->weightedRandom($prizes->all(), 'weight');

        return new GachaPrizeDto(
            contentType: $prize->getAttribute('content_type'),
            contentId: $prize->getAttribute('content_id'),
            amount: $prize->getAttribute('amount'),
            rarity: $rarity,
            isGuaranteed: false
        );
    }

    /**
     * 重み付きランダム抽選
     *
     * @param array $items
     * @param string $weightKey
     * @return mixed
     */
    private function weightedRandom(array $items, string $weightKey)
    {
        if (empty($items)) {
            throw new \Exception('No items available for weighted random selection');
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
