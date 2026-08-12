<?php

namespace Tests\Unit\Middleware;

use Illuminate\Http\Request;
use NexusSecurity\Middleware\VerifyClientSignature;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * クライアント署名の秘密鍵バリデーションのテスト
 *
 * リポジトリが公開されているため、サンプル値の CLIENT_SECRET は誰でも知り得る。
 * そのまま本番相当の環境で使われると署名検証が意味をなさないため、
 * local以外では起動時に落とす。
 */
class VerifyClientSignatureSecretTest extends TestCase
{
    private VerifyClientSignature $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new VerifyClientSignature;
    }

    /**
     * 署名検証を通そうとするリクエストを作る
     */
    private function makeSignedRequest(): Request
    {
        $request = Request::create('/api/auth/sign_up', 'POST', [], [], [], [], '{}');
        $request->headers->set('X-Client-Timestamp', (string) time());
        $request->headers->set('X-Client-Nonce', str_repeat('a', 32));
        $request->headers->set('X-Client-Signature', str_repeat('b', 64));

        return $request;
    }

    #[Test]
    public function test_missing_secret_is_rejected(): void
    {
        config(['app.env' => 'production']);
        config(['security.client_signature.secret' => '']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not configured/');

        $this->middleware->handle($this->makeSignedRequest(), fn ($r) => response('ok'));
    }

    #[Test]
    public function test_sample_secret_is_rejected_outside_local(): void
    {
        config(['app.env' => 'production']);
        config(['security.client_signature.secret' => 'your-secret-key-here-change-in-production']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/publicly known/');

        $this->middleware->handle($this->makeSignedRequest(), fn ($r) => response('ok'));
    }

    #[Test]
    public function test_short_secret_is_rejected_outside_local(): void
    {
        config(['app.env' => 'staging']);
        config(['security.client_signature.secret' => 'short']);

        $this->expectException(\RuntimeException::class);

        $this->middleware->handle($this->makeSignedRequest(), fn ($r) => response('ok'));
    }

    #[Test]
    public function test_sample_secret_is_tolerated_in_local(): void
    {
        config(['app.env' => 'local']);
        config(['security.client_signature.secret' => 'your-secret-key-here-change-in-production']);

        // localではサンプル値でも例外にはならない（開発の利便のため）
        // 署名不一致で401になるが、RuntimeExceptionは投げられない
        $response = $this->middleware->handle($this->makeSignedRequest(), fn ($r) => response('ok'));

        $this->assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function test_strong_secret_passes_validation(): void
    {
        config(['app.env' => 'production']);
        // openssl rand -hex 32 相当の64文字
        config(['security.client_signature.secret' => str_repeat('0123456789abcdef', 4)]);

        // 鍵の検証は通り、署名不一致による401まで進む
        $response = $this->middleware->handle($this->makeSignedRequest(), fn ($r) => response('ok'));

        $this->assertSame(401, $response->getStatusCode());
    }
}
