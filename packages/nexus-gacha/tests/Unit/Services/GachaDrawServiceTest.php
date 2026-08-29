<?php

namespace NexusGacha\Tests\Unit\Services;

use Nexus\Core\Support\CustomCollection;
use NexusGacha\Exceptions\GachaDrawException;
use NexusGacha\Repositories\GachaPrizeRepositoryInterface;
use NexusGacha\Repositories\GachaRarityRateRepositoryInterface;
use NexusGacha\Repositories\GachaStepBonusContentRepositoryInterface;
use NexusGacha\Repositories\GachaStepBonusRepositoryInterface;
use NexusGacha\Repositories\GachaStepRepositoryInterface;
use NexusGacha\Services\GachaDrawService;
use NexusGacha\Strategies\GachaDrawContext;
use NexusGacha\Strategies\GachaDrawStrategyInterface;
use NexusGacha\ValueObjects\GachaPrize;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * GachaDrawService のテスト
 *
 * 抽選そのもの（レアリティ→景品）は Strategy 側にテストがある。
 * ここは組み立て — 何連引くか、ステップアップのボーナスを
 * どの位置に差し込むか、対応するStrategyをどう選ぶか。
 *
 * 引いた本数が合わない・確定枠が入らないは、そのまま苦情になる。
 */
class GachaDrawServiceTest extends TestCase
{
    private const GACHA_ID = 'gacha_001';

    private GachaRarityRateRepositoryInterface&MockObject $rarityRateRepository;

    private GachaPrizeRepositoryInterface&MockObject $prizeRepository;

    private GachaStepRepositoryInterface&MockObject $stepRepository;

    private GachaStepBonusRepositoryInterface&MockObject $stepBonusRepository;

    private GachaDrawService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rarityRateRepository = $this->createMock(GachaRarityRateRepositoryInterface::class);
        $this->prizeRepository = $this->createMock(GachaPrizeRepositoryInterface::class);
        $this->stepRepository = $this->createMock(GachaStepRepositoryInterface::class);
        $this->stepBonusRepository = $this->createMock(GachaStepBonusRepositoryInterface::class);

        // 通常抽選が常にレアリティ1の景品を返すようにしておく
        $this->rarityRateRepository->method('selectByGachaId')
            ->willReturn(new CustomCollection([$this->rarityRate(1, 100)]));
        $this->prizeRepository->method('selectByGachaIdAndRarity')
            ->willReturnCallback(fn (string $gachaId, int $rarity) => new CustomCollection([
                $this->prize('Item', "item_rarity_{$rarity}", 1, 100),
            ]));

        $this->service = new GachaDrawService(
            $this->rarityRateRepository,
            $this->prizeRepository,
            $this->stepRepository,
            $this->stepBonusRepository,
            $this->createMock(GachaStepBonusContentRepositoryInterface::class),
        );
    }

    // ========================================
    // 通常のガチャ
    // ========================================

    #[Test]
    public function 引いた回数ぶんの景品が返る(): void
    {
        $prizes = $this->service->draw(self::GACHA_ID, drawCount: 10, hasStepUp: false, currentStep: 0);

        $this->assertCount(10, $prizes);
        $this->assertContainsOnlyInstancesOf(GachaPrize::class, $prizes);
    }

    #[Test]
    public function 通常抽選に確定フラグは立たない(): void
    {
        $prizes = $this->service->draw(self::GACHA_ID, drawCount: 3, hasStepUp: false, currentStep: 0);

        foreach ($prizes as $prize) {
            $this->assertFalse($prize->isGuaranteed());
        }
    }

    #[Test]
    public function ステップアップでなければステップを引きに行かない(): void
    {
        $this->stepRepository->expects($this->never())->method('selectByGachaIdAndNumber');

        $this->service->draw(self::GACHA_ID, drawCount: 1, hasStepUp: false, currentStep: 3);
    }

    #[Test]
    public function 引く回数が0なら景品も0件(): void
    {
        $this->assertSame([], $this->service->draw(self::GACHA_ID, drawCount: 0, hasStepUp: false, currentStep: 0));
    }

    // ========================================
    // ステップアップ
    // ========================================

    #[Test]
    public function ステップが見つからなければ通常抽選だけになる(): void
    {
        $this->stepRepository->method('selectByGachaIdAndNumber')->willReturn(null);
        $this->stepBonusRepository->expects($this->never())->method('selectByStepId');

        $prizes = $this->service->draw(self::GACHA_ID, drawCount: 5, hasStepUp: true, currentStep: 99);

        $this->assertCount(5, $prizes);
        foreach ($prizes as $prize) {
            $this->assertFalse($prize->isGuaranteed());
        }
    }

    #[Test]
    public function 指定した位置にボーナス景品が入る(): void
    {
        // 「10連目はSSR確定」の形
        $this->givenStepBonuses([$this->stepBonus(position: 10, bonusRarity: 5)]);

        $prizes = $this->service->draw(self::GACHA_ID, drawCount: 10, hasStepUp: true, currentStep: 1);

        $this->assertCount(10, $prizes);
        $this->assertSame(5, $prizes[9]->getRarity(), '10連目がレアリティ5');
        $this->assertTrue($prizes[9]->isGuaranteed());

        // 他の枠は通常抽選のまま
        $this->assertFalse($prizes[0]->isGuaranteed());
        $this->assertFalse($prizes[8]->isGuaranteed());
    }

    #[Test]
    public function 複数の位置にボーナスを置ける(): void
    {
        $this->givenStepBonuses([
            $this->stepBonus(position: 1, bonusRarity: 4),
            $this->stepBonus(position: 5, bonusRarity: 5),
        ]);

        $prizes = $this->service->draw(self::GACHA_ID, drawCount: 5, hasStepUp: true, currentStep: 1);

        $this->assertSame(4, $prizes[0]->getRarity());
        $this->assertSame(5, $prizes[4]->getRarity());
        $this->assertFalse($prizes[2]->isGuaranteed());
    }

    #[Test]
    public function 引く回数を超える位置のボーナスは入らない(): void
    {
        // 10連目確定のステップを1連で引いた場合
        $this->givenStepBonuses([$this->stepBonus(position: 10, bonusRarity: 5)]);

        $prizes = $this->service->draw(self::GACHA_ID, drawCount: 1, hasStepUp: true, currentStep: 1);

        $this->assertCount(1, $prizes);
        $this->assertFalse($prizes[0]->isGuaranteed());
    }

    #[Test]
    public function 位置0のボーナスは既存の枠を置き換える(): void
    {
        // position=0 は「どこかの枠が確定に変わる」扱い。
        // 差し込みではなく置き換えなので、総数は引いた回数のまま
        $this->givenStepBonuses([$this->stepBonus(position: 0, bonusRarity: 5, bonusCount: 2)]);

        $prizes = $this->service->draw(self::GACHA_ID, drawCount: 10, hasStepUp: true, currentStep: 1);

        $this->assertCount(10, $prizes, '本数は増えない');

        $guaranteed = array_filter($prizes, fn (GachaPrize $prize) => $prize->isGuaranteed());
        $this->assertGreaterThanOrEqual(1, count($guaranteed), '確定枠が入る');
        $this->assertLessThanOrEqual(2, count($guaranteed), '同じ枠に重なると2つ目は消える');
    }

    // ========================================
    // Strategy の選択
    // ========================================

    #[Test]
    public function 対応するstrategyが無ければ例外になる(): void
    {
        $this->givenStepBonuses([$this->stepBonus(position: 1, bonusRarity: 5, selectionType: 'no_such_type')]);

        $this->expectException(GachaDrawException::class);
        $this->expectExceptionMessage('Unsupported selection type: no_such_type');

        $this->service->draw(self::GACHA_ID, drawCount: 1, hasStepUp: true, currentStep: 1);
    }

    #[Test]
    public function 独自のstrategyを足せる(): void
    {
        $this->service->registerStrategy(new FixedPrizeStrategy);
        $this->givenStepBonuses([$this->stepBonus(position: 1, bonusRarity: null, selectionType: 'fixed')]);

        $prizes = $this->service->draw(self::GACHA_ID, drawCount: 1, hasStepUp: true, currentStep: 1);

        $this->assertSame('fixed_prize', $prizes[0]->getContentMstId());
    }

    #[Test]
    public function 後から足したstrategyは既定のものを上書きしない(): void
    {
        // findHandlerと同じく、先に登録されたものが優先される
        $this->service->registerStrategy(new FixedPrizeStrategy('none'));
        $this->givenStepBonuses([$this->stepBonus(position: 1, bonusRarity: 5)]);

        $prizes = $this->service->draw(self::GACHA_ID, drawCount: 1, hasStepUp: true, currentStep: 1);

        $this->assertNotSame('fixed_prize', $prizes[0]->getContentMstId(), '既定のNoneDrawStrategyが使われる');
    }

    /**
     * ステップとそのボーナス景品を用意する
     *
     * @param  list<object>  $bonuses
     */
    private function givenStepBonuses(array $bonuses): void
    {
        $this->stepRepository->method('selectByGachaIdAndNumber')
            ->willReturn($this->step('step_001'));
        $this->stepBonusRepository->method('selectByStepId')
            ->with('step_001')
            ->willReturn(new CustomCollection($bonuses));
    }

    private function step(string $id): object
    {
        return new class($id)
        {
            public function __construct(private string $id) {}

            public function getAttribute(string $key): mixed
            {
                return $key === 'id' ? $this->id : null;
            }
        };
    }

    private function stepBonus(
        int $position,
        ?int $bonusRarity,
        int $bonusCount = 1,
        string $selectionType = 'none',
    ): object {
        return new class($position, $bonusRarity, $bonusCount, $selectionType)
        {
            public function __construct(
                public int $position,
                private ?int $bonusRarity,
                private int $bonusCount,
                private string $selectionType,
            ) {}

            public function getAttribute(string $key): mixed
            {
                return match ($key) {
                    'position' => $this->position,
                    'bonus_rarity' => $this->bonusRarity,
                    'bonus_count' => $this->bonusCount,
                    'selection_type' => $this->selectionType,
                    'is_pickup_only' => false,
                    default => null,
                };
            }
        };
    }

    private function prize(string $contentType, string $contentMstId, int $amount, int $weight): object
    {
        return new class($contentType, $contentMstId, $amount, $weight)
        {
            public function __construct(
                private string $contentType,
                private string $contentMstId,
                private int $amount,
                private int $weight,
            ) {}

            public function getAttribute(string $key): mixed
            {
                return match ($key) {
                    'content_type' => $this->contentType,
                    'content_mst_id' => $this->contentMstId,
                    'amount' => $this->amount,
                    'weight' => $this->weight,
                    default => null,
                };
            }
        };
    }

    private function rarityRate(int $rarity, int $rate): object
    {
        return new class($rarity, $rate)
        {
            public function __construct(private int $rarity, private int $rate) {}

            public function getAttribute(string $key): mixed
            {
                return match ($key) {
                    'rarity' => $this->rarity,
                    'rate' => $this->rate,
                    default => null,
                };
            }
        };
    }
}

/**
 * 常に同じ景品を返すだけのStrategy（登録できることの確認用）
 */
class FixedPrizeStrategy implements GachaDrawStrategyInterface
{
    public function __construct(private string $selectionType = 'fixed') {}

    public function supports(string $selectionType): bool
    {
        return $selectionType === $this->selectionType;
    }

    public function draw(
        mixed $bonus,
        ?string $selectedCandidateId,
        string $mstGachaId,
        GachaDrawContext $context
    ): GachaPrize {
        return new GachaPrize(
            contentType: 'Item',
            contentMstId: 'fixed_prize',
            amount: 1,
            rarity: 5,
            isGuaranteed: true,
        );
    }
}
