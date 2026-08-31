<?php

namespace Tests\Unit\Models;

use App\Models\Sys\SysPlayerDevice;
use Nexus\Core\Utilities\ClockUtility;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SysPlayerDevice のテスト
 *
 * 端末UUIDでプレイヤーを引き当てる、サインインの入口。
 * UUIDが空のまま照合が通ると、別人の端末として扱われかねない。
 *
 * device_info はJSON列で、機種変更の追跡やサポート対応で見る。
 */
class SysPlayerDeviceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-03-15 12:00:00');
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 値を設定して読み出せる(): void
    {
        $device = new SysPlayerDevice;
        $device->setSysPlayerId(42);
        $device->setUuid('device-uuid-001');
        $device->setDeviceInfo(['os' => 'iOS', 'version' => '18.0']);

        $this->assertSame(42, $device->getSysPlayerId());
        $this->assertSame('device-uuid-001', $device->getUuid());
        $this->assertSame(['os' => 'iOS', 'version' => '18.0'], $device->getDeviceInfo());
    }

    #[Test]
    public function 未設定のuuidは内部用と外部用で返し方が違う(): void
    {
        // 認証パッケージの契約は string を求めるため空文字に寄せる。
        // 内部では「未設定」と「空」を区別したいので nullable 版を持つ
        $device = new SysPlayerDevice;

        $this->assertNull($device->getUuidNullable());
        $this->assertSame('', $device->getUuid());
    }

    #[Test]
    public function device_infoは未設定ならnull(): void
    {
        $this->assertNull((new SysPlayerDevice)->getDeviceInfo());
    }

    #[Test]
    public function device_infoは入れ子でも保てる(): void
    {
        $device = new SysPlayerDevice;
        $info = ['os' => 'Android', 'hardware' => ['model' => 'Pixel', 'ram_mb' => 8192]];

        $device->setDeviceInfo($info);

        $this->assertSame($info, $device->getDeviceInfo());
    }

    // ========================================
    // 最終ログイン日時
    // ========================================

    #[Test]
    public function 最終ログイン日時は未設定ならnull(): void
    {
        $device = new SysPlayerDevice;

        $this->assertNull($device->getLastLoginAt());
        $this->assertNull($device->getLastLoginAtDateTime());
    }

    #[Test]
    public function 最終ログイン日時を設定して読み出せる(): void
    {
        $device = new SysPlayerDevice;
        $device->setLastLoginAt('2026-03-14 09:30:00');

        $this->assertSame('2026-03-14 09:30:00', $device->getLastLoginAt());
    }

    #[Test]
    public function 最終ログイン日時は現在時刻で打刻できる(): void
    {
        // 打刻はClockUtility経由。テストで時刻を固定できる必要がある
        $device = new SysPlayerDevice;

        $device->markLastLoginAt();

        $this->assertSame('2026-03-15 12:00:00', $device->getLastLoginAt());
    }

    #[Test]
    public function 最終ログイン日時はdatetimeでも渡せる(): void
    {
        $device = new SysPlayerDevice;
        $device->setLastLoginAt(new \DateTimeImmutable('2026-03-14 09:30:00'));

        $this->assertSame('2026-03-14 09:30:00', $device->getLastLoginAt());
    }

    // ========================================
    // 認証パッケージ向けの入口
    // ========================================

    #[Test]
    public function 認証パッケージの契約を満たす(): void
    {
        $device = new SysPlayerDevice;
        $device->setAttribute('id', 7);
        $device->setSysPlayerId(42);
        $device->setUuid('device-uuid-001');
        $device->setAttribute('created_at', '2026-03-01 00:00:00');
        $device->setAttribute('updated_at', '2026-03-15 12:00:00');

        $this->assertSame(7, $device->getId());
        $this->assertSame(42, $device->getPlayerId());
        $this->assertSame('2026-03-01 00:00:00', $device->getCreatedAt());
        $this->assertSame('2026-03-15 12:00:00', $device->getUpdatedAt());
    }

    #[Test]
    public function レスポンス用の配列ではidに接頭辞が付く(): void
    {
        $device = new SysPlayerDevice;
        $device->setAttribute('id', 7);
        $device->setUuid('device-uuid-001');

        $array = $device->toResponseArray();

        $this->assertSame(7, $array['sys_player_device_id']);
        $this->assertArrayNotHasKey('id', $array);
        $this->assertArrayNotHasKey('uuid', $array, '端末UUIDはクライアントに返さない');
    }
}
