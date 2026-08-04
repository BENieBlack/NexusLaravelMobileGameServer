<?php

namespace NexusGacha\Strategies;

use NexusGacha\Repositories\GachaStepBonusContentRepositoryInterface;
use NexusGacha\Repositories\GachaPrizeRepositoryInterface;
use NexusGacha\Repositories\GachaRarityRateRepositoryInterface;

/**
 * GachaDrawContext
 * 
 * ガチャ抽選Strategyに必要な依存オブジェクトを渡すためのコンテキストオブジェクト
 * 
 * このクラスを使用することで、Strategyインターフェースのメソッドシグネチャを
 * 簡潔に保ちつつ、必要な依存を渡すことができます。
 */
class GachaDrawContext
{
    public function __construct(
        public readonly GachaStepBonusContentRepositoryInterface $bonusContentRepository,
        public readonly GachaPrizeRepositoryInterface $prizeRepository,
        public readonly GachaRarityRateRepositoryInterface $rarityRateRepository,
    ) {
    }
}
