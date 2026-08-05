<?php

namespace Tests\Unit\Domain\Gacha\Services;

use NexusResource\DTOs\ResourceDto;
use NexusResourceDelivery\DTOs\ResourceDeliveryResultDto;
use NexusResourceDelivery\Services\ResourceDeliveryService;
use NexusGacha\Services\GachaPrizeService as PackageGachaPrizeService;
use NexusGacha\Dto\GachaPrizeDto;
use App\Domain\Gacha\Services\GachaPrizeService;
use Mockery;
use Tests\TestCase;

class GachaPrizeServiceTest extends TestCase
{
    protected GachaPrizeService $service;
    protected PackageGachaPrizeService $mockBasePrizeService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // NexusGacha\Services\GachaPrizeServiceをモック
        $this->mockBasePrizeService = Mockery::mock(PackageGachaPrizeService::class);
        
        // GachaPrizeServiceを作成
        $this->service = new GachaPrizeService($this->mockBasePrizeService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a successful DeliveryResult for testing
     */
    protected function createSuccessResult(int $count): DeliveryResult
    {
        return new DeliveryResult(
            deliveredItemArray: [],
            failedItemArray: [],
            totalCount: $count,
            successCount: $count,
            failedCount: 0
        );
    }

    /**
     * Test grantPrizes calls DeliveryService with correct item content
     */
    public function test_grant_prizes_with_single_item_prize(): void
    {
        // Arrange
        $prizes = [
            [
                'content_type' => 'item',
                'content_id' => 'item_potion_001',
                'amount' => 5,
                'rarity' => 3,
                'is_guaranteed' => false,
            ],
        ];

        // Assert - DeliveryService::addContents and deliver should be called
        $this->mockBasePrizeService->shouldReceive('grantPrizes')
            ->once()
            ->with(1, Mockery::on(function ($arg) {
                return is_array($arg) 
                    && count($arg) === 1
                    && $arg[0] instanceof GachaPrizeDto
                    && $arg[0]->getContentType() === 'item'
                    && $arg[0]->getContentId() === 'item_potion_001'
                    && $arg[0]->getAmount() === 5;
            }));

        // Act
        $this->service->grantPrizes(1, $prizes);
        
        // Assert - mock expectations were met
        $this->assertTrue(true);
    }

    /**
     * Test grantPrizes with single unit prize
     */
    public function test_grant_prizes_with_single_unit_prize(): void
    {
        // Arrange
        $prizes = [
            [
                'content_type' => 'unit',
                'content_id' => 'unit_hero_001',
                'amount' => 1,
                'rarity' => 5,
                'is_guaranteed' => true,
            ],
        ];

        // Assert
        $this->mockBasePrizeService->shouldReceive('grantPrizes')
            ->once()
            ->with(1, Mockery::on(function ($arg) {
                return is_array($arg) 
                    && count($arg) === 1
                    && $arg[0] instanceof GachaPrizeDto
                    && $arg[0]->getContentType() === 'unit'
                    && $arg[0]->getContentId() === 'unit_hero_001'
                    && $arg[0]->getAmount() === 1;
            }));

        // Act
        $this->service->grantPrizes(1, $prizes);
        
        // Assert - mock expectations were met
        $this->assertTrue(true);
    }

    /**
     * Test grantPrizes with single equipment prize
     */
    public function test_grant_prizes_with_single_equipment_prize(): void
    {
        // Arrange
        $prizes = [
            [
                'content_type' => 'equipment',
                'content_id' => 'equipment_sword_001',
                'amount' => 1,
                'rarity' => 4,
                'is_guaranteed' => false,
            ],
        ];

        // Assert
        $this->mockBasePrizeService->shouldReceive('grantPrizes')
            ->once()
            ->with(1, Mockery::on(function ($arg) {
                return is_array($arg) 
                    && count($arg) === 1
                    && $arg[0] instanceof GachaPrizeDto
                    && $arg[0]->getContentType() === 'equipment'
                    && $arg[0]->getContentId() === 'equipment_sword_001'
                    && $arg[0]->getAmount() === 1;
            }));

        // Act
        $this->service->grantPrizes(1, $prizes);
        
        // Assert - mock expectations were met
        $this->assertTrue(true);
    }

    /**
     * Test grantPrizes with multiple prizes
     */
    public function test_grant_prizes_with_mixed_prize_types(): void
    {
        // Arrange
        $prizes = [
            [
                'content_type' => 'item',
                'content_id' => 'item_gold_001',
                'amount' => 100,
                'rarity' => 1,
                'is_guaranteed' => false,
            ],
            [
                'content_type' => 'unit',
                'content_id' => 'unit_warrior_002',
                'amount' => 1,
                'rarity' => 4,
                'is_guaranteed' => false,
            ],
            [
                'content_type' => 'equipment',
                'content_id' => 'equipment_shield_003',
                'amount' => 2,
                'rarity' => 3,
                'is_guaranteed' => false,
            ],
        ];

        // Assert
        $this->mockBasePrizeService->shouldReceive('grantPrizes')
            ->once()
            ->with(1, Mockery::on(function ($arg) {
                return is_array($arg) 
                    && count($arg) === 3
                    && $arg[0] instanceof GachaPrizeDto
                    && $arg[0]->getContentType() === 'item'
                    && $arg[1] instanceof GachaPrizeDto
                    && $arg[1]->getContentType() === 'unit'
                    && $arg[2] instanceof GachaPrizeDto
                    && $arg[2]->getContentType() === 'equipment';
            }));

        // Act
        $this->service->grantPrizes(1, $prizes);
        
        // Assert - mock expectations were met
        $this->assertTrue(true);
    }

    /**
     * Test grantPrizes with empty prizes array
     */
    public function test_grant_prizes_with_empty_prizes_array(): void
    {
        // Arrange
        $prizes = [];

        // Assert
        $this->mockBasePrizeService->shouldReceive('grantPrizes')
            ->once()
            ->with(1, []);

        // Act
        $this->service->grantPrizes(1, $prizes);
        
        // Assert - mock expectations were met
        $this->assertTrue(true);
    }

    /**
     * Test grantPrizes throws exception for unsupported content type
     */
    public function test_grant_prizes_throws_exception_for_unsupported_content_type(): void
    {
        // Arrange
        $prizes = [
            [
                'content_type' => 'unsupported_type',
                'content_id' => 'some_id',
                'amount' => 1,
                'rarity' => 1,
                'is_guaranteed' => false,
            ],
        ];

        // Assert - ベースサービスが例外をスローする
        $this->mockBasePrizeService->shouldReceive('grantPrizes')
            ->once()
            ->with(1, Mockery::any())
            ->andThrow(new \Exception('Unsupported content type: unsupported_type'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported content type: unsupported_type');

        // Act
        $this->service->grantPrizes(1, $prizes);
    }

    /**
     * Test grantPrizes uses correct player ID
     */
    public function test_grant_prizes_uses_correct_player_id(): void
    {
        // Arrange
        $prizes = [
            [
                'content_type' => 'item',
                'content_id' => 'item_crystal_005',
                'amount' => 10,
                'rarity' => 2,
                'is_guaranteed' => false,
            ],
        ];

        // Assert
        $this->mockBasePrizeService->shouldReceive('grantPrizes')
            ->once()
            ->with(42, Mockery::any());

        // Act
        $this->service->grantPrizes(42, $prizes);
        
        // Assert - mock expectations were met
        $this->assertTrue(true);
    }
}
