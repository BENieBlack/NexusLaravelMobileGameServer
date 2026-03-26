<?php

namespace Tests\Unit\Utilities;

use App\Utilities\ApiSession;
use App\Utilities\Clock;
use Tests\TestCase;

/**
 * ApiSessionの動作テスト
 *
 * ApiSessionクラスが正しく動作することを確認します
 * - プレイヤーID管理
 * - リクエスト開始時刻（now）管理
 */
class ApiSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Clock::initialize();
        ApiSession::clearForTest();
    }

    protected function tearDown(): void
    {
        ApiSession::clearForTest();
        parent::tearDown();
    }

    /** @test */
    public function 初期状態ではプレイヤーIDと時刻は設定されていない(): void
    {
        $this->assertFalse(ApiSession::hasSysPlayerId());
        $this->assertFalse(ApiSession::hasNow());
    }

    /** @test */
    public function プレイヤーIDと時刻の設定ができる(): void
    {
        ApiSession::setSysPlayerId(123);
        
        $this->assertTrue(ApiSession::hasSysPlayerId());
        $this->assertTrue(ApiSession::hasNow());
    }

    /** @test */
    public function プレイヤーIDの取得ができる(): void
    {
        ApiSession::setSysPlayerId(123);
        
        $playerId = ApiSession::getSysPlayerId();
        
        $this->assertSame(123, $playerId);
    }

    /** @test */
    public function リクエスト開始時刻の取得ができる(): void
    {
        ApiSession::setSysPlayerId(123);
        
        $now = ApiSession::getNow();
        $clockNow = Clock::now();
        
        $this->assertTrue($now->equalTo($clockNow));
    }

    /** @test */
    public function インスタンスメソッドでの操作ができる(): void
    {
        ApiSession::setSysPlayerId(123);
        
        $session = app(ApiSession::class);
        
        $this->assertSame(123, $session->getPlayerId());
        $this->assertTrue($session->hasPlayerId());
        $this->assertNotNull($session->getNowValue());
        $this->assertTrue($session->hasNowValue());
        
        $session->setPlayerId(456);
        
        $this->assertSame(456, $session->getPlayerId());
    }

    /** @test */
    public function 静的メソッドで更新された値を確認できる(): void
    {
        ApiSession::setSysPlayerId(123);
        $session = app(ApiSession::class);
        $session->setPlayerId(456);
        
        $playerId = ApiSession::getSysPlayerId();
        
        $this->assertSame(456, $playerId);
    }

    /** @test */
    public function クリア機能が動作する(): void
    {
        ApiSession::setSysPlayerId(123);
        
        ApiSession::clearForTest();
        
        $this->assertFalse(ApiSession::hasSysPlayerId());
        $this->assertFalse(ApiSession::hasNow());
    }

    /** @test */
    public function 未設定時にプレイヤーID取得で例外がスローされる(): void
    {
        $this->expectException(\RuntimeException::class);
        
        ApiSession::getSysPlayerId();
    }

    /** @test */
    public function 未設定時に時刻取得で例外がスローされる(): void
    {
        $this->expectException(\RuntimeException::class);
        
        ApiSession::getNow();
    }

    /** @test */
    public function コンストラクタでプレイヤーIDと時刻を設定できる(): void
    {
        $customNow = Clock::now()->addHours(1);
        $newSession = new ApiSession(789, $customNow);
        
        $this->assertSame(789, $newSession->getPlayerId());
        $this->assertTrue($newSession->getNowValue()->equalTo($customNow));
    }
}
