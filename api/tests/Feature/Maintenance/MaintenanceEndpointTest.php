<?php

namespace Tests\Feature\Maintenance;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * メンテナンス関連エンドポイントの検証
 *
 * status は認証不要、start / end は管理者トークンが必要。
 */
class MaintenanceEndpointTest extends TestCase
{
    use RefreshMultipleDatabases;

    private const ADMIN_TOKEN = 'test-admin-token-0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('auth.admin_token', self::ADMIN_TOKEN);
        Config::set('auth.admin_allowed_ips', null);
    }

    #[Test]
    public function test_status_is_accessible_without_authentication(): void
    {
        $response = $this->getJson('/api/maintenance/status');

        $response->assertOk();
    }

    #[Test]
    public function test_start_requires_admin_token(): void
    {
        $this->postJson('/api/admin/maintenance/start', [
            'title' => 'メンテナンス',
            'message' => '実施中です',
        ])->assertStatus(401);
    }

    #[Test]
    public function test_start_and_end_maintenance(): void
    {
        $headers = $this->authHeaders(self::ADMIN_TOKEN);

        $start = $this->withHeaders($headers)->postJson('/api/admin/maintenance/start', [
            'title' => 'メンテナンス',
            'message' => '実施中です',
        ]);
        $start->assertOk();

        // 開始後はメンテナンス中として扱われる
        $status = $this->getJson('/api/maintenance/status');
        $status->assertOk();

        $end = $this->withHeaders($headers)->postJson('/api/admin/maintenance/end');
        $end->assertOk();
    }

    #[Test]
    public function test_start_rejects_end_before_start(): void
    {
        $this->withHeaders($this->authHeaders(self::ADMIN_TOKEN))
            ->postJson('/api/admin/maintenance/start', [
                'start_at' => '2026-01-02 00:00:00',
                'end_at' => '2026-01-01 00:00:00',
            ])
            ->assertStatus(422);
    }
}
