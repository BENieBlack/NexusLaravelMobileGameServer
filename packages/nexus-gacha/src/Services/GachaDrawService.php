<?php

namespace NexusGacha\Services;

use NexusGacha\ValueObjects\GachaPrize;
use NexusGacha\Exceptions\GachaDrawException;
use NexusGacha\Repositories\GachaRarityRateRepositoryInterface;
use NexusGacha\Repositories\GachaPrizeRepositoryInterface;
use NexusGacha\Repositories\GachaStepRepositoryInterface;
use NexusGacha\Repositories\GachaStepBonusRepositoryInterface;
use NexusGacha\Repositories\GachaStepBonusContentRepositoryInterface;
use NexusGacha\Strategies\GachaDrawStrategyInterface;
use NexusGacha\Strategies\GachaDrawContext;
use NexusGacha\Strategies\ChoiceDrawStrategy;
use NexusGacha\Strategies\RandomDrawStrategy;
use NexusGacha\Strategies\NoneDrawStrategy;

/**
 * GachaDrawService
 * 
 * ガチャの抽選ロジックを担当するサービス
 * 
 * Strategy Patternを使用して、selection_typeごとの抽選ロジックを分離しています。
 * 新しい抽選タイプを追加する場合は、GachaDrawStrategyInterfaceを実装した
 * 新しいStrategyクラスを作成し、registerStrategy()で登録してください。
 */
class GachaDrawService
{
    /** @var GachaDrawStrategyInterface[] */
    private array $strategies = [];
    
    private readonly GachaDrawContext $context;
    
    public function __construct(
        private readonly GachaRarityRateRepositoryInterface $rarityRateRepository,
        private readonly GachaPrizeRepositoryInterface $prizeRepository,
        private readonly GachaStepRepositoryInterface $stepRepository,
        private readonly GachaStepBonusRepositoryInterface $stepBonusRepository,
        private readonly GachaStepBonusContentRepositoryInterface $stepBonusContentRepository,
    ) {
        // Contextオブジェクトを作成
        $this->context = new GachaDrawContext(
            bonusContentRepository: $this->stepBonusContentRepository,
            prizeRepository: $this->prizeRepository,
            rarityRateRepository: $this->rarityRateRepository,
        );
        
        // デフォルトのStrategyを登録
        $this->registerStrategy(new ChoiceDrawStrategy());
        $this->registerStrategy(new RandomDrawStrategy());
        $this->registerStrategy(new NoneDrawStrategy());
    }
    
    /**
     * 新しい抽選Strategyを登録
     * 
     * @param GachaDrawStrategyInterface $strategy 登録するStrategy
     * @return void
     */
    public function registerStrategy(GachaDrawStrategyInterface $strategy): void
    {
        $this->strategies[] = $strategy;
    }

    /**
     * ガチャを実行して景品リストを取得
     *
     * @param string $mstGachaId
     * @param int $drawCount
     * @param bool $hasStepUp
     * @param int $currentStep
     * @param string|null $selectedCandidateId
     * @return array<GachaPrize>
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
     * @param string $mstGachaId ガチャID
     * @return GachaPrize 抽選結果
     * @throws GachaDrawException 抽選に失敗した場合
     */
    private function drawNormal(string $mstGachaId): GachaPrize
    {
        // NoneDrawStrategyを使用して通常抽選を実行
        $noneStrategy = new NoneDrawStrategy();
        
        // bonus_rarityとis_pickup_onlyを持たないダミーボーナスを作成
        $dummyBonus = new class {
            public function getAttribute(string $key): mixed {
                return match($key) {
                    'bonus_rarity' => null,
                    'is_pickup_only' => false,
                    'selection_type' => 'none',
                    default => null,
                };
            }
        };
        
        return $noneStrategy->draw($dummyBonus, null, $mstGachaId, $this->context);
    }

    /**
     * ボーナス景品抽選
     *
     * @param mixed $bonus ボーナス情報
     * @param string|null $selectedCandidateId ユーザーが選択したコンテンツID
     * @param string $mstGachaId ガチャID
     * @return GachaPrize 抽選結果
     * @throws GachaDrawException 抽選に失敗した場合
     */
    private function drawBonus($bonus, ?string $selectedCandidateId, string $mstGachaId): GachaPrize
    {
        $selectionType = $bonus->getAttribute('selection_type');
        
        // 対応するStrategyを検索
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($selectionType)) {
                return $strategy->draw($bonus, $selectedCandidateId, $mstGachaId, $this->context);
            }
        }
        
        // 対応するStrategyが見つからない場合
        throw new GachaDrawException(
            "Unsupported selection type: {$selectionType}",
            GachaDrawException::CODE_UNSUPPORTED_TYPE
        );
    }
}
