<?php

namespace LaravelMobileBilling\Tests\Unit\DTOs;

use LaravelMobileBilling\DTOs\SubscriptionDto;
use PHPUnit\Framework\TestCase;

class SubscriptionDtoTest extends TestCase
{
    public function test_construct_with_active_subscription()
    {
        $expiresAt = '2026-08-13 10:00:00';
        
        $status = new SubscriptionDto(
            isActive: true,
            expiresAt: $expiresAt,
            autoRenew: true,
            state: 'active'
        );

        $this->assertTrue($status->isActive);
        $this->assertSame($expiresAt, $status->expiresAt);
        $this->assertTrue($status->autoRenew);
        $this->assertSame('active', $status->state);
        $this->assertNull($status->cancelledAt);
    }

    public function test_construct_with_cancelled_subscription()
    {
        $expiresAt = '2026-08-13 10:00:00';
        $cancelledAt = '2026-07-13 15:00:00';
        
        $status = new SubscriptionDto(
            isActive: false,
            expiresAt: $expiresAt,
            autoRenew: false,
            state: 'cancelled',
            cancelledAt: $cancelledAt
        );

        $this->assertFalse($status->isActive);
        $this->assertFalse($status->autoRenew);
        $this->assertSame('cancelled', $status->state);
        $this->assertSame($cancelledAt, $status->cancelledAt);
    }

    public function test_to_array()
    {
        $expiresAt = '2026-08-13 10:00:00';
        $cancelledAt = '2026-07-13 15:00:00';
        
        $status = new SubscriptionDto(
            isActive: true,
            expiresAt: $expiresAt,
            autoRenew: true,
            state: 'active',
            cancelledAt: $cancelledAt
        );

        $array = $status->toArray();

        $this->assertIsArray($array);
        $this->assertTrue($array['is_active']);
        $this->assertSame($expiresAt, $array['expires_at']);
        $this->assertTrue($array['auto_renew']);
        $this->assertSame('active', $array['state']);
        $this->assertSame($cancelledAt, $array['cancelled_at']);
    }

    public function test_to_array_without_cancelled_at()
    {
        $expiresAt = '2026-08-13 10:00:00';
        
        $status = new SubscriptionDto(
            isActive: true,
            expiresAt: $expiresAt,
            autoRenew: true
        );

        $array = $status->toArray();

        $this->assertNull($array['cancelled_at']);
    }

    public function test_to_json()
    {
        $expiresAt = '2026-08-13 10:00:00';
        
        $status = new SubscriptionDto(
            isActive: true,
            expiresAt: $expiresAt,
            autoRenew: false,
            state: 'expired'
        );

        $json = $status->toJson();

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertTrue($decoded['is_active']);
        $this->assertSame($expiresAt, $decoded['expires_at']);
        $this->assertFalse($decoded['auto_renew']);
        $this->assertSame('expired', $decoded['state']);
    }
}
