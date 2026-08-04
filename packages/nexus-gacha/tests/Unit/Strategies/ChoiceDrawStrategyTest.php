<?php

namespace NexusGacha\Tests\Unit\Strategies;

use NexusGacha\Strategies\ChoiceDrawStrategy;
use NexusGacha\Strategies\GachaDrawContext;
use NexusGacha\Exceptions\GachaDrawException;
use NexusGacha\Repositories\GachaStepBonusContentRepositoryInterface;
use NexusGacha\Repositories\GachaPrizeRepositoryInterface;
use NexusGacha\Repositories\GachaRarityRateRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * ChoiceDrawStrategy テスト
 * 
 * ユーザー選択型抽選のテスト
 */
class ChoiceDrawStrategyTest extends TestCase
{
    private ChoiceDrawStrategy $strategy;
    private GachaStepBonusContentRepositoryInterface&MockObject $bonusContentRepository;
    private GachaPrizeRepositoryInterface&MockObject $prizeRepository;
    private GachaRarityRateRepositoryInterface&MockObject $rarityRateRepository;
    private GachaDrawContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->strategy = new ChoiceDrawStrategy();
        
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

    public function test_supports_returns_true_for_choice_type(): void
    {
        $this->assertTrue($this->strategy->supports('choice'));
    }

    public function test_supports_returns_false_for_other_types(): void
    {
        $this->assertFalse($this->strategy->supports('random'));
        $this->assertFalse($this->strategy->supports('none'));
        $this->assertFalse($this->strategy->supports('unknown'));
    }

    public function test_draw_throws_exception_when_selected_candidate_id_is_null(): void
    {
        $this->expectException(GachaDrawException::class);
        $this->expectExceptionMessage('Selected candidate ID is required for choice type');
        $this->expectExceptionCode(GachaDrawException::CODE_MISSING_CANDIDATE_ID);

        $bonus = $this->createBonusMock('choice', 5, false);
        
        $this->strategy->draw($bonus, null, 'gacha_001', $this->context);
    }

    public function test_draw_throws_exception_when_candidate_not_found(): void
    {
        $this->expectException(GachaDrawException::class);
        $this->expectExceptionMessage('Invalid candidate ID');
        $this->expectExceptionCode(GachaDrawException::CODE_INVALID_CANDIDATE);

        $bonus = $this->createBonusMock('choice', 5, false, 'bonus_001');
        
        // findByIdでnullを返す
        $this->bonusContentRepository
            ->expects($this->once())
            ->method('findById')
            ->with('candidate_001')
            ->willReturn(null);
        
        $this->strategy->draw($bonus, 'candidate_001', 'gacha_001', $this->context);
    }

    public function test_draw_throws_exception_when_candidate_bonus_id_mismatch(): void
    {
        $this->expectException(GachaDrawException::class);
        $this->expectExceptionMessage('Invalid candidate ID');
        $this->expectExceptionCode(GachaDrawException::CODE_INVALID_CANDIDATE);

        $bonus = $this->createBonusMock('choice', 5, false, 'bonus_001');
        
        // 異なるbonus_idを持つcandidateを返す
        $candidate = $this->createCandidateMock('bonus_002', 'Item', 'item_001', 100);
        
        $this->bonusContentRepository
            ->expects($this->once())
            ->method('findById')
            ->with('candidate_001')
            ->willReturn($candidate);
        
        $this->strategy->draw($bonus, 'candidate_001', 'gacha_001', $this->context);
    }

    public function test_draw_returns_prize_dto_with_correct_values(): void
    {
        $bonus = $this->createBonusMock('choice', 5, false, 'bonus_001');
        $candidate = $this->createCandidateMock('bonus_001', 'Unit', 'unit_ssr_001', 500);
        
        $this->bonusContentRepository
            ->expects($this->once())
            ->method('findById')
            ->with('candidate_001')
            ->willReturn($candidate);
        
        $result = $this->strategy->draw($bonus, 'candidate_001', 'gacha_001', $this->context);
        
        $this->assertSame('Unit', $result->getContentType());
        $this->assertSame('unit_ssr_001', $result->getContentId());
        $this->assertSame(500, $result->getAmount());
        $this->assertSame(5, $result->getRarity());
        $this->assertTrue($result->isGuaranteed());
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
    private function createCandidateMock(string $bonusId, string $contentType, string $contentId, int $amount): object
    {
        return new class($bonusId, $contentType, $contentId, $amount) {
            public function __construct(
                private string $bonusId,
                private string $contentType,
                private string $contentId,
                private int $amount
            ) {}
            
            public function getAttribute(string $key): mixed {
                return match($key) {
                    'mst_gacha_step_bonus_id' => $this->bonusId,
                    'content_type' => $this->contentType,
                    'content_id' => $this->contentId,
                    'amount' => $this->amount,
                    default => null,
                };
            }
        };
    }
}
