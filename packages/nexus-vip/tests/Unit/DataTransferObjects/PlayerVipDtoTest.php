<?php

namespace NexusVip\Tests\Unit\DataTransferObjects;

use NexusVip\DataTransferObjects\PlayerVip;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * PlayerVipのユニットテスト
 */
class PlayerVipDtoTest extends TestCase
{
    /**
     * DTOを正常に作成できる
     */
    #[Test]
    public function dt_oを正常に作成できる(): void
    {
        // Act
        $dto = new PlayerVip(
            sysPlayerId: 1,
            vipPoint: 500,
            totalPaidAmount: 5000.0
        );

        // Assert
        $this->assertSame(1, $dto->getSysPlayerId());
        $this->assertSame(500, $dto->getVipPoint());
        $this->assertSame(5000.0, $dto->getTotalPaidAmount());
    }

    /**
     * VIPポイントを設定できる
     */
    #[Test]
    public function vi_pポイントを設定できる(): void
    {
        // Arrange
        $dto = new PlayerVip(
            sysPlayerId: 1,
            vipPoint: 100,
            totalPaidAmount: 1000.0
        );

        // Act
        $dto->setVipPoint(200);

        // Assert
        $this->assertSame(200, $dto->getVipPoint());
    }

    /**
     * 累積課金額を設定できる
     */
    #[Test]
    public function 累積課金額を設定できる(): void
    {
        // Arrange
        $dto = new PlayerVip(
            sysPlayerId: 1,
            vipPoint: 100,
            totalPaidAmount: 1000.0
        );

        // Act
        $dto->setTotalPaidAmount(2000.0);

        // Assert
        $this->assertSame(2000.0, $dto->getTotalPaidAmount());
    }

    /**
     * VIPポイントを加算できる
     */
    #[Test]
    public function vi_pポイントを加算できる(): void
    {
        // Arrange
        $dto = new PlayerVip(
            sysPlayerId: 1,
            vipPoint: 100,
            totalPaidAmount: 1000.0
        );

        // Act
        $dto->addVipPoint(50);

        // Assert
        $this->assertSame(150, $dto->getVipPoint());
    }

    /**
     * 累積課金額を加算できる
     */
    #[Test]
    public function 累積課金額を加算できる(): void
    {
        // Arrange
        $dto = new PlayerVip(
            sysPlayerId: 1,
            vipPoint: 100,
            totalPaidAmount: 1000.0
        );

        // Act
        $dto->addTotalPaidAmount(500.0);

        // Assert
        $this->assertSame(1500.0, $dto->getTotalPaidAmount());
    }

    /**
     * 配列に変換できる
     */
    #[Test]
    public function 配列に変換できる(): void
    {
        // Arrange
        $dto = new PlayerVip(
            sysPlayerId: 123,
            vipPoint: 999,
            totalPaidAmount: 9999.99
        );

        // Act
        $array = $dto->toArray();

        // Assert
        $this->assertIsArray($array);
        $this->assertArrayHasKey('sys_player_id', $array);
        $this->assertArrayHasKey('vip_point', $array);
        $this->assertArrayHasKey('total_paid_amount', $array);
        $this->assertSame(123, $array['sys_player_id']);
        $this->assertSame(999, $array['vip_point']);
        $this->assertSame(9999.99, $array['total_paid_amount']);
    }

    /**
     * ゼロ値で作成できる
     */
    #[Test]
    public function ゼロ値で作成できる(): void
    {
        // Act
        $dto = new PlayerVip(
            sysPlayerId: 1,
            vipPoint: 0,
            totalPaidAmount: 0.0
        );

        // Assert
        $this->assertSame(0, $dto->getVipPoint());
        $this->assertSame(0.0, $dto->getTotalPaidAmount());
    }
}
