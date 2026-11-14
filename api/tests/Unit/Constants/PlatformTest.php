<?php

namespace Tests\Unit\Constants;

use App\Constants\Platform;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    #[Test]
    public function 許可値はappleとgoogleの2つ(): void
    {
        $this->assertSame(['Apple', 'Google'], Platform::values());
    }

    #[Test]
    public function 完全一致のみ許可する(): void
    {
        $this->assertTrue(Platform::isValid('Apple'));
        $this->assertTrue(Platform::isValid('Google'));

        foreach ([null, '', 'apple', 'GOOGLE', 'iOS', 'Android', 'Windows', ' Apple', 'Apple '] as $value) {
            $this->assertFalse(Platform::isValid($value), var_export($value, true));
        }
    }
}
