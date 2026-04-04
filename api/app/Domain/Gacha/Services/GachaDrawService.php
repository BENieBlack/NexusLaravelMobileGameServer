<?php

namespace App\Domain\Gacha\Services;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Models\Mst\MstGachaPrize;
use App\Repositories\Mst\MstGachaPrizeRepository;
use App\Repositories\Mst\MstGachaRarityRateRepository;
use App\Repositories\Mst\MstGachaStepGuaranteedCandidateRepository;
use App\Repositories\Mst\MstGachaStepGuaranteedRepository;
use App\Repositories\Mst\MstGachaStepRepository;

/**
 * GachaDrawService
 *
 * ガチャの抽選ロジックを担当するサービス
 */
class GachaDrawService
{
    public function __construct(
        private readonly MstGachaRarityRateRepository $rarityRateRepository,
        private readonly MstGachaPrizeRepository $prizeRepository,
        private readonly MstGachaStepRepository $stepRepository,
        private readonly MstGachaStepGuaranteedRepository $stepGuaranteedRepository,
        private readonly MstGachaStepGuaranteedCandidateRepository $candidateRepository,
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
     * @return array
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
        $stepGuaranteedList = [];
        if ($hasStepUp) {
            $step = $this->stepRepository->selectByGachaIdAndStepNumber($mstGachaId, $currentStep);
            if ($step) {
                // ステップの確定景品リストを取得
                $stepGuaranteedList = $this->stepGuaranteedRepository->queryOrMemory()
                    ->where('mst_gacha_step_id', $step->getAttribute('id'))
                    ->where('is_active', true)
                    ->sortBy('position')
                    ->values()
                    ->all();
            }
        }

        // 通常抽選とステップ確定を組み合わせて実行
        for ($i = 0; $i < $drawCount; $i++) {
            $position = $i + 1;
            
            // この位置に確定景品があるかチェック
            $guaranteed = collect($stepGuaranteedList)->firstWhere('position', $position);
            
            if ($guaranteed) {
                // 確定景品を抽選
                $prize = $this->drawGuaranteed($guaranteed, $selectedCandidateId, $mstGachaId);
            } else {
                // 通常抽選
                $prize = $this->drawNormal($mstGachaId);
            }
            
            $prizes[] = $prize;
        }

        // position=0（ランダム位置）の確定景品を処理
        $randomGuaranteedList = collect($stepGuaranteedList)->where('position', 0)->values();
        foreach ($randomGuaranteedList as $guaranteed) {
            for ($i = 0; $i < $guaranteed->getAttribute('guaranteed_count'); $i++) {
                $prize = $this->drawGuaranteed($guaranteed, $selectedCandidateId, $mstGachaId);
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
     * @return array
     */
    private function drawNormal(string $mstGachaId): array
    {
        // 1. レアリティ抽選
        $rarity = $this->drawRarity($mstGachaId);
        
        // 2. 景品抽選
        return $this->drawPrize($mstGachaId, $rarity, false);
    }

    /**
     * 確定景品抽選
     *
     * @param mixed $guaranteed
     * @param string|null $selectedCandidateId
     * @param string $mstGachaId
     * @return array
     * @throws GameException
     */
    private function drawGuaranteed($guaranteed, ?string $selectedCandidateId, string $mstGachaId): array
    {
        $selectionType = $guaranteed->getAttribute('selection_type');
        $guaranteedRarity = $guaranteed->getAttribute('guaranteed_rarity');
        $isPickupOnly = $guaranteed->getAttribute('is_pickup_only');

        if ($selectionType === 'choice') {
            // ユーザー選択
            if (!$selectedCandidateId) {
                throw new GameException(
                    GameErrorCode::GACHA_CANDIDATE_REQUIRED,
                    "Selected candidate ID is required for choice type"
                );
            }

            $candidate = $this->candidateRepository->selectById($selectedCandidateId);
            if (!$candidate || $candidate->getAttribute('mst_gacha_step_guaranteed_id') !== $guaranteed->getAttribute('id')) {
                throw new GameException(
                    GameErrorCode::GACHA_CANDIDATE_NOT_FOUND,
                    "Invalid candidate ID"
                );
            }

            return [
                'content_type' => $candidate->getAttribute('content_type'),
                'content_id' => $candidate->getAttribute('content_id'),
                'amount' => $candidate->getAttribute('amount'),
                'rarity' => $guaranteedRarity,
                'is_guaranteed' => true,
            ];
        } elseif ($selectionType === 'random') {
            // 候補からランダム
            $candidates = $this->candidateRepository->selectListByGuaranteedId($guaranteed->getAttribute('id'));
            
            if ($candidates->isEmpty()) {
                throw new GameException(
                    GameErrorCode::GACHA_CANDIDATE_NOT_FOUND,
                    "No candidates found for random selection"
                );
            }

            $candidate = $this->weightedRandom($candidates->all(), 'weight');

            return [
                'content_type' => $candidate->getAttribute('content_type'),
                'content_id' => $candidate->getAttribute('content_id'),
                'amount' => $candidate->getAttribute('amount'),
                'rarity' => $guaranteedRarity,
                'is_guaranteed' => true,
            ];
        } else {
            // none: 通常抽選だが確定レアリティ
            if ($guaranteedRarity) {
                return $this->drawPrize($mstGachaId, $guaranteedRarity, $isPickupOnly);
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
        $rarityRates = $this->rarityRateRepository->selectListByGachaId($mstGachaId);
        
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
     * @return array
     */
    private function drawPrize(string $mstGachaId, int $rarity, bool $pickupOnly): array
    {
        $prizes = $this->prizeRepository->selectListByGachaIdAndRarity($mstGachaId, $rarity, $pickupOnly);
        
        if ($prizes->isEmpty()) {
            // ピックアップのみで景品がない場合は通常景品から
            if ($pickupOnly) {
                $prizes = $this->prizeRepository->selectListByGachaIdAndRarity($mstGachaId, $rarity, false);
            }
        }

        $prize = $this->weightedRandom($prizes->all(), 'weight');

        return [
            'content_type' => $prize->getAttribute('content_type'),
            'content_id' => $prize->getAttribute('content_id'),
            'amount' => $prize->getAttribute('amount'),
            'rarity' => $rarity,
            'is_guaranteed' => false,
        ];
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
            throw new GameException(
                GameErrorCode::GACHA_NO_PRIZES_AVAILABLE,
                'No items available for weighted random selection'
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
