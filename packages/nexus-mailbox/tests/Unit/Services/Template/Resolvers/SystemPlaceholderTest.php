<?php

namespace NexusMailbox\Tests\Unit\Services\Template\Resolvers;

use Nexus\Core\Utilities\ClockUtility;
use NexusMailbox\Services\Template\Resolvers\SystemPlaceholder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * SystemPlaceholder のユニットテスト
 *
 * 日時系のプレースホルダーはClockUtility経由で解決されるため、
 * 固定時刻を差し込んで検証する。
 */
class SystemPlaceholderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::reset();
        ClockUtility::setNow('2026-03-17 09:05:30');
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function サポートするキーを申告する(): void
    {
        $resolver = new SystemPlaceholder;

        $this->assertSame(
            ['timestamp', 'date', 'time', 'server_name', 'version'],
            $resolver->supportedKeys()
        );

        foreach ($resolver->supportedKeys() as $key) {
            $this->assertTrue($resolver->supports($key), "{$key} をサポートしていない");
        }
    }

    #[Test]
    public function サポート外のキーはfalseを返す(): void
    {
        $this->assertFalse((new SystemPlaceholder)->supports('player_name'));
    }

    #[Test]
    public function 日時系のプレースホルダーを解決する(): void
    {
        $resolver = new SystemPlaceholder;

        $this->assertSame('2026-03-17 09:05:30', $resolver->resolve('timestamp', []));
        $this->assertSame('2026-03-17', $resolver->resolve('date', []));
        $this->assertSame('09:05:30', $resolver->resolve('time', []));
    }

    #[Test]
    public function サーバー名とバージョンはコンストラクタの値を返す(): void
    {
        $resolver = new SystemPlaceholder('Nexus Server', '2.3.4');

        $this->assertSame('Nexus Server', $resolver->resolve('server_name', []));
        $this->assertSame('2.3.4', $resolver->resolve('version', []));
    }

    #[Test]
    public function サーバー名とバージョンには既定値がある(): void
    {
        $resolver = new SystemPlaceholder;

        $this->assertSame('Game Server', $resolver->resolve('server_name', []));
        $this->assertSame('1.0.0', $resolver->resolve('version', []));
    }

    #[Test]
    public function サポート外のキーはnullを返す(): void
    {
        $this->assertNull((new SystemPlaceholder)->resolve('player_name', []));
    }
}
