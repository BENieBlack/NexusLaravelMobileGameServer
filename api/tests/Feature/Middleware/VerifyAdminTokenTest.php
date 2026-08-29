<?php

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * VerifyAdminToken のテスト
 *
 * 管理者APIはメンテナンスの開始・終了というサービス全体を止める操作を持つ。
 * ここが素通しになると誰でも操作できてしまうため、
 * 通す条件と弾く条件の両方を押さえる。
 *
 * 検証は「トークン設定 → Authorizationヘッダ → トークン一致 → IP制限」の順。
 */
class VerifyAdminTokenTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const ADMIN_TOKEN = 'test-admin-token-0123456789abcdef';

    /**
     * 管理者APIの入口。ミドルウェアを通ることだけが目的なので、
     * 中身が軽いエンドポイントを使う。
     */
    private const ADMIN_URL = '/api/admin/maintenance/end';

    protected function setUp(): void
    {
        parent::setUp();

        Log::spy();
        Config::set('auth.admin_token', self::ADMIN_TOKEN);
        Config::set('auth.admin_allowed_ips', null);
    }

    #[Test]
    public function 正しいトークンなら通す(): void
    {
        $this->withHeaders($this->authHeaders(self::ADMIN_TOKEN))
            ->postJson(self::ADMIN_URL)
            ->assertOk();
    }

    #[Test]
    public function トークンが未設定ならサーバー設定エラーにする(): void
    {
        // ADMIN_TOKEN を設定し忘れたまま公開すると、
        // 素通しではなく必ず落ちるようにしておく
        Config::set('auth.admin_token', null);

        $this->withHeaders($this->authHeaders(self::ADMIN_TOKEN))
            ->postJson(self::ADMIN_URL)
            ->assertStatus(500)
            ->assertJsonPath('error_code', 10500);
    }

    #[Test]
    public function authorizationヘッダが無ければ弾く(): void
    {
        $this->postJson(self::ADMIN_URL)
            ->assertStatus(401)
            ->assertJsonPath('error_code', 10401);
    }

    #[Test]
    public function bearer以外のスキームは弾く(): void
    {
        $this->withHeaders(['Authorization' => 'Basic '.self::ADMIN_TOKEN])
            ->postJson(self::ADMIN_URL)
            ->assertStatus(401)
            ->assertJsonPath('error_code', 10401);
    }

    #[Test]
    public function トークンが違えば弾く(): void
    {
        $this->withHeaders($this->authHeaders('wrong-admin-token'))
            ->postJson(self::ADMIN_URL)
            ->assertStatus(401)
            ->assertJsonPath('error_code', 10401);
    }

    #[Test]
    public function トークンの前方一致では通さない(): void
    {
        // hash_equals による比較なので、前半が一致していても通らない
        $this->withHeaders($this->authHeaders(substr(self::ADMIN_TOKEN, 0, 10)))
            ->postJson(self::ADMIN_URL)
            ->assertStatus(401);
    }

    #[Test]
    public function 空のトークンは弾く(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '])
            ->postJson(self::ADMIN_URL)
            ->assertStatus(401);
    }

    #[Test]
    public function ip制限が未設定なら誰でも通す(): void
    {
        Config::set('auth.admin_allowed_ips', '');

        $this->withHeaders($this->authHeaders(self::ADMIN_TOKEN))
            ->postJson(self::ADMIN_URL)
            ->assertOk();
    }

    #[Test]
    public function 許可ipからは通す(): void
    {
        Config::set('auth.admin_allowed_ips', '127.0.0.1');

        $this->withHeaders($this->authHeaders(self::ADMIN_TOKEN))
            ->postJson(self::ADMIN_URL, [], ['REMOTE_ADDR' => '127.0.0.1'])
            ->assertOk();
    }

    #[Test]
    public function 許可されていないipは弾く(): void
    {
        Config::set('auth.admin_allowed_ips', '10.0.0.1');

        $this->withHeaders($this->authHeaders(self::ADMIN_TOKEN))
            ->postJson(self::ADMIN_URL)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 10403);
    }

    #[Test]
    public function 許可ipは複数指定でき前後の空白を無視する(): void
    {
        Config::set('auth.admin_allowed_ips', ' 10.0.0.1 , 127.0.0.1 ');

        $this->withHeaders($this->authHeaders(self::ADMIN_TOKEN))
            ->postJson(self::ADMIN_URL, [], ['REMOTE_ADDR' => '127.0.0.1'])
            ->assertOk();
    }

    #[Test]
    public function トークンが違えばip制限より先に弾く(): void
    {
        // 許可IPからのアクセスでも、トークンが違えば401
        Config::set('auth.admin_allowed_ips', '127.0.0.1');

        $this->withHeaders($this->authHeaders('wrong-admin-token'))
            ->postJson(self::ADMIN_URL, [], ['REMOTE_ADDR' => '127.0.0.1'])
            ->assertStatus(401);
    }
}
