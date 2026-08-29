<?php

namespace Tests\Unit\Tidb;

use Illuminate\Support\Facades\Config;
use NexusTidb\Support\TidbMode;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TidbMode の設定読み取りのテスト
 *
 * env() を直接読むと config:cache 済みの本番で null になるため、
 * config 経由で解決していることをここで固定する。
 */
class TidbModeTest extends TestCase
{
    protected function tearDown(): void
    {
        TidbMode::resetForTest();

        parent::tearDown();
    }

    #[Test]
    public function 既定ではtidbとして扱わない(): void
    {
        $this->assertFalse(config('nexus-tidb.is_tidb'), 'パッケージの既定値');
        $this->assertFalse(TidbMode::isEnabled());
    }

    #[Test]
    public function 設定ファイルから読む(): void
    {
        Config::set('nexus-tidb.is_tidb', true);

        $this->assertTrue(TidbMode::isEnabled());
    }

    #[Test]
    public function 設定が無ければ無効にする(): void
    {
        Config::set('nexus-tidb.is_tidb', null);

        $this->assertFalse(TidbMode::isEnabled());
    }

    #[Test]
    public function テスト用の上書きが設定より優先される(): void
    {
        Config::set('nexus-tidb.is_tidb', false);
        TidbMode::fakeForTest(true);

        $this->assertTrue(TidbMode::isEnabled());

        TidbMode::resetForTest();
        $this->assertFalse(TidbMode::isEnabled());
    }
}
