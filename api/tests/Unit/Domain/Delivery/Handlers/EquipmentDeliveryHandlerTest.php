<?php

namespace Tests\Unit\Domain\Delivery\Handlers;

use App\Domain\Delivery\Constants\DeliveryConst;
use App\Domain\Delivery\DTOs\DeliveryContent;
use App\Domain\Delivery\Handlers\EquipmentDeliveryHandler;
use App\Repositories\Trx\TrxEquipmentRepository;
use App\Persistence\ApiSession;
use App\Utilities\Clock;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

class EquipmentDeliveryHandlerTest extends TestCase
{
    use RefreshMultipleDatabases;

    protected EquipmentDeliveryHandler $handler;
    protected TrxEquipmentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        // ApiSessionを初期化
        Clock::initialize();
        ApiSession::setSysPlayerId(1);
        
        $this->repository = new TrxEquipmentRepository();
        $this->handler = new EquipmentDeliveryHandler($this->repository);
    }

    /**
     * Test handler supports equipment type
     */
    public function test_supports_equipment_type(): void
    {
        // Assert
        $this->assertTrue($this->handler->supports(DeliveryConst::CONTENT_TYPE_EQUIPMENT));
        $this->assertFalse($this->handler->supports(DeliveryConst::CONTENT_TYPE_ITEM));
        $this->assertFalse($this->handler->supports(DeliveryConst::CONTENT_TYPE_UNIT));
        $this->assertFalse($this->handler->supports(DeliveryConst::CONTENT_TYPE_DIAMOND));
        $this->assertFalse($this->handler->supports(DeliveryConst::CONTENT_TYPE_WALLET));
    }

    /**
     * Test handle creates single equipment
     */
    public function test_handle_creates_single_equipment(): void
    {
        // Arrange
        $content = DeliveryContent::equipment('equipment_sword_001', 1);

        // Act
        $this->handler->handle(1, $content);
        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals('equipment_sword_001', $queuedModels[0]->mst_equipment_id);
        $this->assertEquals(1, $queuedModels[0]->level);
        $this->assertEquals(1, $queuedModels[0]->sys_player_id);
    }

    /**
     * Test handle creates multiple equipment
     */
    public function test_handle_creates_multiple_equipment(): void
    {
        // Arrange
        $content = DeliveryContent::equipment('equipment_shield_002', 3);

        // Act
        $this->handler->handle(1, $content);
        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(3, $queuedModels);
        foreach ($queuedModels as $model) {
            $this->assertEquals('equipment_shield_002', $model->mst_equipment_id);
            $this->assertEquals(1, $model->level); // Default level
            $this->assertEquals(1, $model->sys_player_id);
        }
    }

    /**
     * Test handle with custom level in metadata
     */
    public function test_handle_with_custom_level_in_metadata(): void
    {
        // Arrange
        $content = new DeliveryContent(
            type: DeliveryConst::CONTENT_TYPE_EQUIPMENT,
            id: 'equipment_armor_003',
            amount: 2,
            metadata: ['level' => 5]
        );

        // Act
        $this->handler->handle(1, $content);
        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(2, $queuedModels);
        foreach ($queuedModels as $model) {
            $this->assertEquals('equipment_armor_003', $model->mst_equipment_id);
            $this->assertEquals(5, $model->level);
            $this->assertEquals(1, $model->sys_player_id);
        }
    }

    /**
     * Test handle uses ApiSession for player ID
     */
    public function test_handle_uses_api_session_for_player_id(): void
    {
        // Arrange
        ApiSession::setSysPlayerId(42);
        $repository = new TrxEquipmentRepository();
        $handler = new EquipmentDeliveryHandler($repository);
        
        $content = DeliveryContent::equipment('equipment_helmet_004', 1);

        // Act
        $handler->handle(42, $content);
        $queuedModels = array_values($repository->getQueuedModels());

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals(42, $queuedModels[0]->sys_player_id);
    }

    /**
     * Test handle creates equipment with default level when metadata is null
     */
    public function test_handle_creates_equipment_with_default_level_when_metadata_is_null(): void
    {
        // Arrange
        $content = new DeliveryContent(
            type: DeliveryConst::CONTENT_TYPE_EQUIPMENT,
            id: 'equipment_boots_005',
            amount: 1,
            metadata: null
        );

        // Act
        $this->handler->handle(1, $content);
        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals(1, $queuedModels[0]->level); // Default level
    }

    /**
     * Test handle creates equipment with default level when level not in metadata
     */
    public function test_handle_creates_equipment_with_default_level_when_level_not_in_metadata(): void
    {
        // Arrange
        $content = new DeliveryContent(
            type: DeliveryConst::CONTENT_TYPE_EQUIPMENT,
            id: 'equipment_gloves_006',
            amount: 1,
            metadata: ['some_other_field' => 'value']
        );

        // Act
        $this->handler->handle(1, $content);
        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(1, $queuedModels);
        $this->assertEquals(1, $queuedModels[0]->level); // Default level
    }

    /**
     * Test handle with zero amount creates no equipment
     */
    public function test_handle_with_zero_amount_creates_no_equipment(): void
    {
        // Arrange
        $content = DeliveryContent::equipment('equipment_ring_007', 0);

        // Act
        $this->handler->handle(1, $content);
        $queuedModels = array_values($this->repository->getQueuedModels());

        // Assert
        $this->assertCount(0, $queuedModels);
    }
}
