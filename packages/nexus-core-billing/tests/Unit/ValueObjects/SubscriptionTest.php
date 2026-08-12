<?php

namespace NexusBilling\Tests\Unit\ValueObjects;

use NexusBilling\ValueObjects\Subscription;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SubscriptionTest extends TestCase
{
    #[Test]
    public function test_active_subscription()
    {
        $subscription = new Subscription(
            isActive: true,
            expiresAt: '2026-12-31 23:59:59',
            autoRenew: true,
        );

        $this->assertTrue($subscription->isActive());
        $this->assertSame('2026-12-31 23:59:59', $subscription->getExpiresAt());
        $this->assertTrue($subscription->isAutoRenew());
        $this->assertNull($subscription->getState());
        $this->assertNull($subscription->getCancelledAt());
        $this->assertFalse($subscription->isCancelled());
    }

    #[Test]
    public function test_cancelled_subscription()
    {
        $cancelledAt = '2026-08-01 00:00:00';
        $subscription = new Subscription(
            isActive: false,
            expiresAt: '2026-12-31 23:59:59',
            autoRenew: false,
            state: 'cancelled',
            cancelledAt: $cancelledAt,
        );

        $this->assertFalse($subscription->isActive());
        $this->assertFalse($subscription->isAutoRenew());
        $this->assertSame('cancelled', $subscription->getState());
        $this->assertSame($cancelledAt, $subscription->getCancelledAt());
        $this->assertTrue($subscription->isCancelled());
    }

    #[Test]
    public function test_is_expired_at()
    {
        $subscription = new Subscription(
            isActive: true,
            expiresAt: '2026-08-12 00:00:00',
            autoRenew: true,
        );

        $this->assertFalse($subscription->isExpiredAt('2026-08-11 23:59:59'));
        $this->assertTrue($subscription->isExpiredAt('2026-08-12 00:00:00'));
        $this->assertTrue($subscription->isExpiredAt('2026-08-13 00:00:00'));
    }

    #[Test]
    public function test_equals_compares_by_value()
    {
        $a = new Subscription(isActive: true, expiresAt: '2026-12-31 23:59:59', autoRenew: true);
        $b = new Subscription(isActive: true, expiresAt: '2026-12-31 23:59:59', autoRenew: true);
        $c = new Subscription(isActive: true, expiresAt: '2026-12-31 23:59:59', autoRenew: false);

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    #[Test]
    public function test_to_array()
    {
        $subscription = new Subscription(
            isActive: true,
            expiresAt: '2026-12-31 23:59:59',
            autoRenew: true,
            state: 'active',
        );

        $array = $subscription->toArray();

        $this->assertTrue($array['is_active']);
        $this->assertSame('2026-12-31 23:59:59', $array['expires_at']);
        $this->assertTrue($array['auto_renew']);
        $this->assertSame('active', $array['state']);
        $this->assertNull($array['cancelled_at']);
    }
}
