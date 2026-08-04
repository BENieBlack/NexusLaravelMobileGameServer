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
     * ステップIDでステップボーナス景品リストを取得
     * 
     * @param string $stepId
     * @return \Illuminate\Support\Collection
     */
    public function findStepBonusesByStepId(string $stepId): \Illuminate\Support\Collection;

    /**
     * ボーナスIDでコンテンツリストを取得
     * 
     * @param string $bonusId
     * @return \Illuminate\Support\Collection
     */
    public function findCandidatesByBonusId(string $bonusId): \Illuminate\Support\Collection;

    /**
     * コンテンツIDでコンテンツを取得
     * 
     * @param string $candidateId
     * @return mixed|null
     */
    public function findCandidateById(string $candidateId): mixed;
}
