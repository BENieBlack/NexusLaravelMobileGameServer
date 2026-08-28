<?php

namespace NexusGacha\Tests\Unit\Strategies;

use NexusGacha\Strategies\RandomDrawStrategy;
use NexusGacha\Strategies\GachaDrawContext;
use NexusGacha\Exceptions\GachaDrawException;
use NexusGacha\Repositories\GachaStepBonusContentRepositoryInterface;
use NexusGacha\Repositories\GachaPrizeRepositoryInterface;
use NexusGacha\Repositories\GachaRarityRateRepositoryInterface;
use Nexus\Core\Support\CustomCollection;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * RandomDrawStrategy テスト
 * 
 * ランダム選択型抽選のテスト
 */
class RandomDrawStrategyTest extends TestCase
{
    private RandomDrawStrategy $strategy;
    private GachaStepBonusContentRepositoryInterface&MockObject $bonusContentRepository;
    private GachaPrizeRepositoryInterface&MockObject $prizeRepository;
    private GachaRarityRateRepositoryInterface&MockObject $rarityRateRepository;
    private GachaDrawContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->strategy = new RandomDrawStrategy();
        
        // モックRepositoryを作成
        $this->bonusContentRepository = $this->createMock(GachaStepBonusContentRepositoryInterface::class);
        $this->prizeRepository = $this->createMock(GachaPrizeRepositoryInterface::class);
        $this->rarityRateRepository = $this->createMock(GachaRarityRateRepositoryInterface::class);
        
        // Contextを作成
        $this->context = new GachaDrawContext(
            bonusContentRepository: $this->bonusContentRepository,
            prizeRepository: $this->prizeRepository,
            rarityRateRepository: $this->rarityRateRepository,
        );
    }

    public function test_supports_returns_true_for_random_type(): void
    {
        $this->assertTrue($this->strategy->supports('random'));
    }

    public function test_supports_returns_false_for_other_types(): void
    {
        $this->assertFalse($this->strategy->supports('choice'));
        $this->assertFalse($this->strategy->supports('none'));
        $this->assertFalse($this->strategy->supports('unknown'));
    }

    public function test_draw_throws_exception_when_no_candidates_found(): void
    {
        $this->expectException(GachaDrawException::class);
        $this->expectExceptionMessage('No candidates found for random selection');
        $this->expectExceptionCode(GachaDrawException::CODE_NO_CANDIDATES);

        $bonus = $this->createBonusMock('random', 5, false, 'bonus_001');
        
        // 空のCollectionを返す
        $this->bonusContentRepository
            ->expects($this->once())
            ->method('selectByBonusId')
            ->with('bonus_001')
            ->willReturn(new CustomCollection([]));
        
        $this->strategy->draw($bonus, null, 'gacha_001', $this->context);
    }

    public function test_draw_returns_prize_dto_from_candidates(): void
    {
        $bonus = $this->createBonusMock('random', 5, false, 'bonus_001');
        
        // 3つの候補を作成
        $candidates = new CustomCollection([
            $this->createCandidateMock('Item', 'item_001', 100, 50),
            $this->createCandidateMock('Unit', 'unit_ssr_001', 1, 30),
            $this->createCandidateMock('Currency', 'gold', 10000, 20),
        ]);
        
        $this->bonusContentRepository
            ->expects($this->once())
            ->method('selectByBonusId')
            ->with('bonus_001')
            ->willReturn($candidates);
        
        $result = $this->strategy->draw($bonus, null, 'gacha_001', $this->context);
        
        // いずれかの候補が選ばれているはず
        $contentTypes = ['Item', 'Unit', 'Currency'];
        $this->assertContains($result->getContentType(), $contentTypes);
        $this->assertSame(5, $result->getRarity());
        $this->assertTrue($result->isGuaranteed());
    }

    public function test_draw_respects_weight_distribution(): void
    {
        $bonus = $this->createBonusMock('random', 5, false, 'bonus_001');
        
        // 重み付き候補（weight=100のアイテムのみ）
        $candidates = new CustomCollection([
            $this->createCandidateMock('Item', 'guaranteed_item', 999, 100),
            $this->createCandidateMock('Item', 'rare_item', 1, 0), // weight=0なので選ばれない
        ]);
        
        $this->bonusContentRepository
            ->expects($this->once())
            ->method('selectByBonusId')
            ->with('bonus_001')
            ->willReturn($candidates);
        
        $result = $this->strategy->draw($bonus, null, 'gacha_001', $this->context);
        
        // weight=100のアイテムが選ばれるはず
        $this->assertSame('guaranteed_item', $result->getContentMstId());
        $this->assertSame(999, $result->getAmount());
    }

    /**
     * ボーナスのモックを作成
     */
    private function createBonusMock(string $selectionType, int $bonusRarity, bool $isPickupOnly, ?string $bonusId = null): object
    {
        return new class($selectionType, $bonusRarity, $isPickupOnly, $bonusId) {
            public function __construct(
                private string $selectionType,
                private int $bonusRarity,
                private bool $isPickupOnly,
                private ?string $bonusId
            ) {}
            
            public function getAttribute(string $key): mixed {
                return match($key) {
                    'selection_type' => $this->selectionType,
                    'bonus_rarity' => $this->bonusRarity,
                    'is_pickup_only' => $this->isPickupOnly,
                    'id' => $this->bonusId,
                    default => null,
                };
            }
        };
    }

    /**
     * 候補のモックを作成
     */
    private function createCandidateMock(string $contentType, string $contentMstId, int $amount, int $weight): object
    {
        return new class($contentType, $contentMstId, $amount, $weight) {
            public function __construct(
                private string $contentType,
                private string $contentMstId,
                private int $amount,
                private int $weight
            ) {}
            
            public function getAttribute(string $key): mixed {
                return match($key) {
                    'content_type' => $this->contentType,
                    'content_mst_id' => $this->contentMstId,
                    'amount' => $this->amount,
                    'weight' => $this->weight,
                    default => null,
                };
            }
        };
    }
}
