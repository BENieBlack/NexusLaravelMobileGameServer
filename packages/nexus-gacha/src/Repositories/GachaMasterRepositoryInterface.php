<?php

namespace NexusGacha\Repositories;

/**
 * GachaMasterRepositoryInterface
 * 
 * ガチャマスターデータへのアクセスを抽象化
 * 実装側でEloquent Modelを返すことを想定
 */
interface GachaMasterRepositoryInterface
{
    /**
     * ガチャIDでレアリティ確率リストを取得
     * 
     * @param string $mstGachaId
     * @return \Illuminate\Support\Collection
     */
    public function findRarityRatesByGachaId(string $mstGachaId): \Illuminate\Support\Collection;

    /**
     * ガチャIDとレアリティで景品リストを取得
     * 
     * @param string $mstGachaId
     * @param int $rarity
     * @param bool $pickupOnly
     * @return \Illuminate\Support\Collection
     */
    public function findPrizesByGachaIdAndRarity(string $mstGachaId, int $rarity, bool $pickupOnly): \Illuminate\Support\Collection;

    /**
     * ガチャIDとステップ番号でステップ情報を取得
     * 
     * @param string $mstGachaId
     * @param int $stepNumber
     * @return mixed|null
     */
    public function findStepByGachaIdAndNumber(string $mstGachaId, int $stepNumber): mixed;

    /**
     * ステップIDでステップ確定景品リストを取得
     * 
     * @param string $stepId
     * @return \Illuminate\Support\Collection
     */
    public function findStepGuaranteedsByStepId(string $stepId): \Illuminate\Support\Collection;

    /**
     * 確定景品IDで候補リストを取得
     * 
     * @param string $guaranteedId
     * @return \Illuminate\Support\Collection
     */
    public function findCandidatesByGuaranteedId(string $guaranteedId): \Illuminate\Support\Collection;

    /**
     * 候補IDで候補を取得
     * 
     * @param string $candidateId
     * @return mixed|null
     */
    public function findCandidateById(string $candidateId): mixed;
}
