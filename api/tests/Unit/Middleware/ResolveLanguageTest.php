<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ResolveLanguage;
use App\Persistence\ApiSession;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ResolveLanguageのテスト
 *
 * Accept-Languageから言語を決めてApiSessionに載せる。
 */
class ResolveLanguageTest extends TestCase
{
    private ResolveLanguage $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        $this->middleware = new ResolveLanguage;
    }

    protected function tearDown(): void
    {
        ApiSession::clearForTest();
        parent::tearDown();
    }

    #[Test]
    public function ヘッダーの言語が_api_sessionに入る(): void
    {
        $this->handle('en');

        $this->assertSame('en', ApiSession::getLanguage());
        $this->assertSame('en', app()->getLocale());
    }

    #[Test]
    public function 重み付きの値から最優先の言語を選ぶ(): void
    {
        $this->handle('ko;q=0.7,en;q=0.9');

        $this->assertSame('en', ApiSession::getLanguage());
    }

    #[Test]
    public function 地域付きの言語コードを解釈する(): void
    {
        $this->handle('zh-TW,zh;q=0.9');

        $this->assertSame('zh-TW', ApiSession::getLanguage());
    }

    #[Test]
    public function ヘッダーが無ければ既定の言語になる(): void
    {
        $this->handle(null);

        $this->assertSame(config('language.default'), ApiSession::getLanguage());
    }

    #[Test]
    public function サポート外の言語は既定の言語になる(): void
    {
        $this->handle('sv');

        $this->assertSame(config('language.default'), ApiSession::getLanguage());
    }

    #[Test]
    public function ミドルウェアを通らない場合も既定の言語を返す(): void
    {
        // バッチやCLIなどHTTP経由でない実行
        $this->assertSame(config('language.default'), ApiSession::getLanguage());
    }

    private function handle(?string $acceptLanguage): void
    {
        $request = Request::create('/api/mailbox/list', 'GET');

        // Request::create() は Accept-Language に既定値を入れるため、明示的に消す
        $request->headers->remove('Accept-Language');

        if ($acceptLanguage !== null) {
            $request->headers->set('Accept-Language', $acceptLanguage);
        }

        $this->middleware->handle($request, fn () => new Response);
    }
}
