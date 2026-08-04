<?php

namespace App\Repositories\Mst;

use App\Repositories\Mst\MstGachaRarityRateRepository;
use App\Repositories\Mst\MstGachaPrizeRepository;
use App\Repositories\Mst\MstGachaStepRepository;
use App\Repositories\Mst\MstGachaStepGuaranteedRepository;
use App\Repositories\Mst\MstGachaStepGuaranteedCandidateRepository;
use NexusGacha\Repositories\GachaMasterRepositoryInterface;

/**
 * GachaMasterRepository
 * 
 * ガチャマスターデータへのアクセスを提供するRepository実装
 * 複数のガチャ関連テーブル（mst_gacha_rarity_rate, mst_gacha_prize, mst_gacha_step等）を集約
 */
class GachaMasterRepository implements GachaMasterRepositoryInterface
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
     * {@inheritDoc}
     */
    public function findRarityRatesByGachaId(string $mstGachaId): \Illuminate\Support\Collection
    {
        return $this->rarityRateRepository->selectListByGachaId($mstGachaId);
    }

    /**
     * {@inheritDoc}
     */
    public function findPrizesByGachaIdAndRarity(string $mstGachaId, int $rarity, bool $pickupOnly): \Illuminate\Support\Collection
    {
        return $this->prizeRepository->selectListByGachaIdAndRarity($mstGachaId, $rarity, $pickupOnly);
    }

    /**
     * {@inheritDoc}
     */
    public function findStepByGachaIdAndNumber(string $mstGachaId, int $stepNumber): mixed
    {
        return $this->stepRepository->selectByGachaIdAndStepNumber($mstGachaId, $stepNumber);
    }

    /**
     * {@inheritDoc}
     */
    public function findStepGuaranteedsByStepId(string $stepId): \Illuminate\Support\Collection
    {
        return $this->stepGuaranteedRepository->queryOrMemory()
            ->where('mst_gacha_step_id', $stepId);
    }

    /**
     * {@inheritDoc}
     */
    public function findCandidatesByGuaranteedId(string $guaranteedId): \Illuminate\Support\Collection
    {
        return $this->candidateRepository->selectListByGuaranteedId($guaranteedId);
    }

    /**
     * {@inheritDoc}
     */
    public function findCandidateById(string $candidateId): mixed
    {
        return $this->candidateRepository->selectById($candidateId);
    }
}
