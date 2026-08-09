<?php

namespace NexusVip\Tests\Unit\Services;

use NexusVip\DTOs\VipBenefitDto;
use NexusVip\DTOs\VipConfig;
use NexusVip\Services\VipBenefitService;
use NexusVip\Services\VipLevelService;
use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * VipBenefitServiceのユニットテスト
 */
class VipBenefitServiceTest extends TestCase
{
    private VipLevelService $vipLevelService;
    private VipConfig $config;
    private VipBenefitService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vipLevelService = Mockery::mock(VipLevelService::class);
        
        // テスト用設定（全特典有効）
        $this->config = new VipConfig(
            enablePointLog: true,
            enableLevelUpEvent: true,
            staminaBonusEnabled: true,
            shopDiscountEnabled: true,
            gachaDiscountEnabled: true,
            dailyDiamondEnabled: true,
        );
        
        $this->service = new VipBenefitService($this->vipLevelService, $this->config);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * @test
     * スタミナ上限にVIPボーナスを適用できる
     */
    public function スタミナ上限にVIPボーナスを適用できる(): void
    {
        // Arrange
        $baseMaxStamina = 100;
        $vipLevel = 5;

        $benefitDto = new VipBenefitDto(
            maxStaminaBonus: 50,
            dailyDiamondBonus: 10,
            shopDiscountRate: 0.1,
            gachaDiscountRate: 0.05
        );

        $this->vipLevelService
            ->shouldReceive('getBenefits')
            ->with($vipLevel)
            ->once()
            ->andReturn($benefitDto);

        // Act
        $result = $this->service->applyStaminaBonus($baseMaxStamina, $vipLevel);

        // Assert
        $this->assertSame(150, $result); // 100 + 50
    }

    /**
     * @test
     * スタミナボーナスが無効の場合は基本値を返す
     */
    public function スタミナボーナスが無効の場合は基本値を返す(): void
    {
        // Arrange
        $configDisabled = new VipConfig(
            enablePointLog: true,
            enableLevelUpEvent: true,
            staminaBonusEnabled: false,
            shopDiscountEnabled: true,
            gachaDiscountEnabled: true,
            dailyDiamondEnabled: true,
        );
        $serviceDisabled = new VipBenefitService($this->vipLevelService, $configDisabled);
        
        $baseMaxStamina = 100;
        $vipLevel = 5;

        // Act
        $result = $serviceDisabled->applyStaminaBonus($baseMaxStamina, $vipLevel);

        // Assert
        $this->assertSame(100, $result);
    }

    /**
     * @test
     * ショップ価格にVIP割引を適用できる
     */
    public function ショップ価格にVIP割引を適用できる(): void
    {
        // Arrange
        $basePrice = 1000;
        $vipLevel = 5;

        $benefitDto = new VipBenefitDto(
            maxStaminaBonus: 50,
            dailyDiamondBonus: 10,
            shopDiscountRate: 0.1,
            gachaDiscountRate: 0.05
        );

        $this->vipLevelService
            ->shouldReceive('getBenefits')
            ->with($vipLevel)
            ->once()
            ->andReturn($benefitDto);

        // Act
        $result = $this->service->applyShopDiscount($basePrice, $vipLevel);

        // Assert
        $this->assertSame(900, $result); // 1000 - (1000 * 0.1)
    }

    /**
     * @test
     * ショップ割引後の価格は最低1
     */
    public function ショップ割引後の価格は最低1(): void
    {
        // Arrange
        $basePrice = 5;
        $vipLevel = 10;

        $benefitDto = new VipBenefitDto(
            maxStaminaBonus: 100,
            dailyDiamondBonus: 50,
            shopDiscountRate: 0.9, // 90%割引
            gachaDiscountRate: 0.5
        );

        $this->vipLevelService
            ->shouldReceive('getBenefits')
            ->with($vipLevel)
            ->once()
            ->andReturn($benefitDto);

        // Act
        $result = $this->service->applyShopDiscount($basePrice, $vipLevel);

        // Assert
        $this->assertSame(1, $result); // max(1, floor(5 - 4.5))
    }

    /**
     * @test
     * ショップ割引が無効の場合は基本価格を返す
     */
    public function ショップ割引が無効の場合は基本価格を返す(): void
    {
        // Arrange
        $configDisabled = new VipConfig(
            enablePointLog: true,
            enableLevelUpEvent: true,
            staminaBonusEnabled: true,
            shopDiscountEnabled: false,
            gachaDiscountEnabled: true,
            dailyDiamondEnabled: true,
        );
        $serviceDisabled = new VipBenefitService($this->vipLevelService, $configDisabled);
        
        $basePrice = 1000;
        $vipLevel = 5;

        // Act
        $result = $serviceDisabled->applyShopDiscount($basePrice, $vipLevel);

        // Assert
        $this->assertSame(1000, $result);
    }

    /**
     * @test
     * ガチャ価格にVIP割引を適用できる
     */
    public function ガチャ価格にVIP割引を適用できる(): void
    {
        // Arrange
        $basePrice = 300;
        $vipLevel = 5;

        $benefitDto = new VipBenefitDto(
            maxStaminaBonus: 50,
            dailyDiamondBonus: 10,
            shopDiscountRate: 0.1,
            gachaDiscountRate: 0.1
        );

        $this->vipLevelService
            ->shouldReceive('getBenefits')
            ->with($vipLevel)
            ->once()
            ->andReturn($benefitDto);

        // Act
        $result = $this->service->applyGachaDiscount($basePrice, $vipLevel);

        // Assert
        $this->assertSame(270, $result); // 300 - (300 * 0.1)
    }

    /**
     * @test
     * ガチャ割引後の価格は最低1
     */
    public function ガチャ割引後の価格は最低1(): void
    {
        // Arrange
        $basePrice = 10;
        $vipLevel = 10;

        $benefitDto = new VipBenefitDto(
            maxStaminaBonus: 100,
            dailyDiamondBonus: 50,
            shopDiscountRate: 0.5,
            gachaDiscountRate: 0.95 // 95%割引
        );

        $this->vipLevelService
            ->shouldReceive('getBenefits')
            ->with($vipLevel)
            ->once()
            ->andReturn($benefitDto);

        // Act
        $result = $this->service->applyGachaDiscount($basePrice, $vipLevel);

        // Assert
        $this->assertSame(1, $result);
    }

    /**
     * @test
     * ガチャ割引が無効の場合は基本価格を返す
     */
    public function ガチャ割引が無効の場合は基本価格を返す(): void
    {
        // Arrange
        $configDisabled = new VipConfig(
            enablePointLog: true,
            enableLevelUpEvent: true,
            staminaBonusEnabled: true,
            shopDiscountEnabled: true,
            gachaDiscountEnabled: false,
            dailyDiamondEnabled: true,
        );
        $serviceDisabled = new VipBenefitService($this->vipLevelService, $configDisabled);
        
        $basePrice = 300;
        $vipLevel = 5;

        // Act
        $result = $serviceDisabled->applyGachaDiscount($basePrice, $vipLevel);

        // Assert
        $this->assertSame(300, $result);
    }

    /**
     * @test
     * デイリーダイヤモンドボーナスを取得できる
     */
    public function デイリーダイヤモンドボーナスを取得できる(): void
    {
        // Arrange
        $vipLevel = 5;

        $benefitDto = new VipBenefitDto(
            maxStaminaBonus: 50,
            dailyDiamondBonus: 20,
            shopDiscountRate: 0.1,
            gachaDiscountRate: 0.05
        );

        $this->vipLevelService
            ->shouldReceive('getBenefits')
            ->with($vipLevel)
            ->once()
            ->andReturn($benefitDto);

        // Act
        $result = $this->service->getDailyDiamondBonus($vipLevel);

        // Assert
        $this->assertSame(20, $result);
    }

    /**
     * @test
     * デイリーダイヤモンドが無効の場合は0を返す
     */
    public function デイリーダイヤモンドが無効の場合は0を返す(): void
    {
        // Arrange
        $configDisabled = new VipConfig(
            enablePointLog: true,
            enableLevelUpEvent: true,
            staminaBonusEnabled: true,
            shopDiscountEnabled: true,
            gachaDiscountEnabled: true,
            dailyDiamondEnabled: false,
        );
        $serviceDisabled = new VipBenefitService($this->vipLevelService, $configDisabled);
        
        $vipLevel = 5;

        // Act
        $result = $serviceDisabled->getDailyDiamondBonus($vipLevel);

        // Assert
        $this->assertSame(0, $result);
    }

    /**
     * @test
     * 汎用割引を適用できる
     */
    public function 汎用割引を適用できる(): void
    {
        // Arrange
        $basePrice = 1000;
        $discountRate = 0.2; // 20%割引

        // Act
        $result = $this->service->applyDiscount($basePrice, $discountRate);

        // Assert
        $this->assertSame(800, $result); // 1000 - (1000 * 0.2)
    }

    /**
     * @test
     * 汎用割引後の価格は最低1
     */
    public function 汎用割引後の価格は最低1(): void
    {
        // Arrange
        $basePrice = 5;
        $discountRate = 1.0; // 100%割引

        // Act
        $result = $this->service->applyDiscount($basePrice, $discountRate);

        // Assert
        $this->assertSame(1, $result);
    }
}
