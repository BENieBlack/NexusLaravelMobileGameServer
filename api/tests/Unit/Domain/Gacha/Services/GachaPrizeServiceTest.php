<?php

namespace Tests\Unit\Domain\Gacha\Services;

use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\Delivery\DTOs\DeliveryResult;
use App\Domain\Delivery\Services\DeliveryService;
use App\Domain\Gacha\Services\GachaPrizeService;
use Mockery;
use Tests\TestCase;

class GachaPrizeServiceTest extends TestCase
{
    protected GachaPrizeService $service;
    protected DeliveryService $mockDeliveryService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // DeliveryServiceをモック
        $this->mockDeliveryService = Mockery::mock(DeliveryService::class);
        
        // GachaPrizeServiceを作成
        $this->service = new GachaPrizeService($this->mockDeliveryService);
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
            ],
        ];

        // Assert - DeliveryService::addContents and deliver should be called
        $this->mockDeliveryService->shouldReceive('addContents')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return is_array($arg) 
                    && count($arg) === 1
                    && $arg[0] instanceof DeliveryContent
                    && $arg[0]->getType() === 'item'
                    && $arg[0]->getId() === 'item_potion_001'
                    && $arg[0]->getAmount() === 5;
            }));

        $this->mockDeliveryService->shouldReceive('deliver')
            ->once()
            ->with(1);

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
            ],
        ];

        // Assert
        $this->mockDeliveryService->shouldReceive('addContents')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return is_array($arg) 
                    && count($arg) === 1
                    && $arg[0] instanceof DeliveryContent
                    && $arg[0]->getType() === 'unit'
                    && $arg[0]->getId() === 'unit_hero_001'
                    && $arg[0]->getAmount() === 1;
            }));

        $this->mockDeliveryService->shouldReceive('deliver')
            ->once()
            ->with(1);

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
            ],
        ];

        // Assert
        $this->mockDeliveryService->shouldReceive('addContents')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return is_array($arg) 
                    && count($arg) === 1
                    && $arg[0] instanceof DeliveryContent
                    && $arg[0]->getType() === 'equipment'
                    && $arg[0]->getId() === 'equipment_sword_001'
                    && $arg[0]->getAmount() === 1;
            }));

        $this->mockDeliveryService->shouldReceive('deliver')
            ->once()
            ->with(1);

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
            ],
            [
                'content_type' => 'unit',
                'content_id' => 'unit_warrior_002',
                'amount' => 1,
            ],
            [
                'content_type' => 'equipment',
                'content_id' => 'equipment_shield_003',
                'amount' => 2,
            ],
        ];

        // Assert
        $this->mockDeliveryService->shouldReceive('addContents')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return is_array($arg) 
                    && count($arg) === 3
                    && $arg[0]->getType() === 'item'
                    && $arg[1]->getType() === 'unit'
                    && $arg[2]->getType() === 'equipment';
            }));

        $this->mockDeliveryService->shouldReceive('deliver')
            ->once()
            ->with(1);

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
        $this->mockDeliveryService->shouldReceive('addContents')
            ->once()
            ->with([]);

        $this->mockDeliveryService->shouldReceive('deliver')
            ->once()
            ->with(1);

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
            ],
        ];

        // Assert
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
            ],
        ];

        // Assert
        $this->mockDeliveryService->shouldReceive('addContents')
            ->once()
            ->with(Mockery::any());

        $this->mockDeliveryService->shouldReceive('deliver')
            ->once()
            ->with(42);

        // Act
        $this->service->grantPrizes(42, $prizes);
        
        // Assert - mock expectations were met
        $this->assertTrue(true);
    }
}
