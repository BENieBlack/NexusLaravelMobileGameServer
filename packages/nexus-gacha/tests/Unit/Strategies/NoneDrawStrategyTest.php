<?php

namespace NexusGacha\Tests\Unit\Strategies;

use NexusGacha\DataTransferObjects\PrizeCandidate;
use NexusGacha\DataTransferObjects\RarityRate;
use NexusGacha\DataTransferObjects\StepBonus;
use NexusGacha\Exceptions\GachaDrawException;
use NexusGacha\Repositories\GachaPrizeRepositoryInterface;
use NexusGacha\Repositories\GachaRarityRateRepositoryInterface;
use NexusGacha\Repositories\GachaStepBonusContentRepositoryInterface;
use NexusGacha\Strategies\GachaDrawContext;
use NexusGacha\Strategies\NoneDrawStrategy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * NoneDrawStrategy テスト
 *
 * 通常抽選型のテスト（レアリティ抽選→景品抽選）
 */
class NoneDrawStrategyTest extends TestCase
{
    private NoneDrawStrategy $strategy;

    private GachaStepBonusContentRepositoryInterface&MockObject $bonusContentRepository;

    private GachaPrizeRepositoryInterface&MockObject $prizeRepository;

    private GachaRarityRateRepositoryInterface&MockObject $rarityRateRepository;

    private GachaDrawContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->strategy = new NoneDrawStrategy;

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

    public function test_supports_returns_true_for_none_type(): void
    {
        $this->assertTrue($this->strategy->supports('none'));
    }

    public function test_supports_returns_false_for_other_types(): void
    {
        $this->assertFalse($this->strategy->supports('choice'));
        $this->assertFalse($this->strategy->supports('random'));
        $this->assertFalse($this->strategy->supports('unknown'));
    }

    public function test_draw_with_bonus_rarity_uses_fixed_rarity(): void
    {
        // bonus_rarity=5が指定されている場合
        $bonus = $this->createBonusMock('none', 5, false);

        // レアリティ5の景品を準備
        $prizes = [
            $this->createPrizeMock('Unit', 'unit_ssr_001', 1, 100),
        ];

        $this->prizeRepository
            ->expects($this->once())
            ->method('selectByGachaIdAndRarity')
            ->with('gacha_001', 5, false)
            ->willReturn($prizes);

        // rarityRateRepositoryは呼ばれないはず
        $this->rarityRateRepository
            ->expects($this->never())
            ->method('selectByGachaId');

        $result = $this->strategy->draw($bonus, null, 'gacha_001', $this->context);

        $this->assertSame('Unit', $result->getContentType());
        $this->assertSame('unit_ssr_001', $result->getContentMstId());
        $this->assertSame(5, $result->getRarity());
        $this->assertTrue($result->isGuaranteed());
    }

    public function test_draw_without_bonus_rarity_performs_rarity_lottery(): void
    {
        // bonus_rarity=nullの場合、レアリティ抽選を行う
        $bonus = $this->createBonusMock('none', null, false);

        // レアリティ確率を準備
        $rarityRates = [
            $this->createRarityRateMock(1, 70),
            $this->createRarityRateMock(2, 20),
            $this->createRarityRateMock(3, 10),
        ];

        $this->rarityRateRepository
            ->expects($this->once())
            ->method('selectByGachaId')
            ->with('gacha_001')
            ->willReturn($rarityRates);

        // 抽選されたレアリティの景品を準備
        $prizes = [
            $this->createPrizeMock('Item', 'item_001', 10, 100),
        ];

        $this->prizeRepository
            ->expects($this->once())
            ->method('selectByGachaIdAndRarity')
            ->with('gacha_001', $this->anything(), false)
            ->willReturn($prizes);

        $result = $this->strategy->draw($bonus, null, 'gacha_001', $this->context);

        $this->assertSame('Item', $result->getContentType());
        $this->assertSame('item_001', $result->getContentMstId());
        $this->assertFalse($result->isGuaranteed()); // 通常抽選なのでfalse
    }

    public function test_draw_with_pickup_only_flag(): void
    {
        // is_pickup_only=trueの場合、ピックアップ景品のみを抽選
        $bonus = $this->createBonusMock('none', 5, true);

        $prizes = [
            $this->createPrizeMock('Unit', 'pickup_unit', 1, 100),
        ];

        $this->prizeRepository
            ->expects($this->once())
            ->method('selectByGachaIdAndRarity')
            ->with('gacha_001', 5, true)
            ->willReturn($prizes);

        $result = $this->strategy->draw($bonus, null, 'gacha_001', $this->context);

        $this->assertSame('pickup_unit', $result->getContentMstId());
    }

    public function test_draw_falls_back_to_normal_prizes_when_no_pickup_prizes(): void
    {
        // is_pickup_only=trueだがピックアップ景品が存在しない場合、通常景品にフォールバック
        $bonus = $this->createBonusMock('none', 5, true);

        // 1回目: ピックアップなし
        // 2回目: 通常景品
        $this->prizeRepository
            ->expects($this->exactly(2))
            ->method('selectByGachaIdAndRarity')
            ->willReturnCallback(function ($gachaId, $rarity, $pickupOnly) {
                if ($pickupOnly) {
                    return []; // ピックアップなし
                }

                return [
                    $this->createPrizeMock('Item', 'normal_item', 10, 100),
                ];
            });

        $result = $this->strategy->draw($bonus, null, 'gacha_001', $this->context);

        $this->assertSame('normal_item', $result->getContentMstId());
    }

    public function test_draw_throws_exception_when_no_prizes_available(): void
    {
        $this->expectException(GachaDrawException::class);
        $this->expectExceptionMessage('No prizes available for selection');
        $this->expectExceptionCode(GachaDrawException::CODE_NO_PRIZES);

        $bonus = $this->createBonusMock('none', 5, false);

        // 景品が存在しない
        $this->prizeRepository
            ->expects($this->once())
            ->method('selectByGachaIdAndRarity')
            ->with('gacha_001', 5, false)
            ->willReturn([]);

        $this->strategy->draw($bonus, null, 'gacha_001', $this->context);
    }

    public function test_rarity_lottery_respects_rate_distribution(): void
    {
        // レアリティ確率の重み付けが正しく動作するかテスト
        $bonus = $this->createBonusMock('none', null, false);

        // レアリティ1が100%、レアリティ2が0%
        $rarityRates = [
            $this->createRarityRateMock(1, 100),
            $this->createRarityRateMock(2, 0),
        ];

        $this->rarityRateRepository
            ->expects($this->once())
            ->method('selectByGachaId')
            ->with('gacha_001')
            ->willReturn($rarityRates);

        $prizes = [
            $this->createPrizeMock('Item', 'common_item', 10, 100),
        ];

        // レアリティ1で抽選されるはず
        $this->prizeRepository
            ->expects($this->once())
            ->method('selectByGachaIdAndRarity')
            ->with('gacha_001', 1, false)
            ->willReturn($prizes);

        $result = $this->strategy->draw($bonus, null, 'gacha_001', $this->context);

        $this->assertSame(1, $result->getRarity());
    }

    /**
     * ボーナスのモックを作成
     */
    private function createBonusMock(string $selectionType, ?int $bonusRarity, bool $isPickupOnly): StepBonus
    {
        return new StepBonus(
            id: 'bonus_001',
            position: 1,
            bonusCount: 1,
            selectionType: $selectionType,
            bonusRarity: $bonusRarity,
            isPickupOnly: $isPickupOnly,
        );
    }

    /**
     * 景品のモックを作成
     */
    private function createPrizeMock(string $contentType, string $contentMstId, int $amount, int $weight): PrizeCandidate
    {
        return new PrizeCandidate(
            contentType: $contentType,
            contentMstId: $contentMstId,
            contentOption: null,
            amount: $amount,
            weight: $weight,
        );
    }

    /**
     * レアリティ確率のモックを作成
     */
    private function createRarityRateMock(int $rarity, int $rate): RarityRate
    {
        return new RarityRate(rarity: $rarity, rate: $rate);
    }
}
