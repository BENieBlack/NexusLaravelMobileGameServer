<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckMaintenance;
use Illuminate\Http\Request;
use Mockery;
use NexusMaintenance\Services\MaintenanceService;
use NexusMaintenance\ValueObjects\Maintenance;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * CheckMaintenanceミドルウェアのテスト
 */
class CheckMaintenanceTest extends TestCase
{
    private MaintenanceService $maintenanceService;

    private CheckMaintenance $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->maintenanceService = Mockery::mock(MaintenanceService::class);
        $this->middleware = new CheckMaintenance($this->maintenanceService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function メンテナンス中でない場合はリクエストを通す(): void
    {
        // Arrange
        $this->maintenanceService
            ->shouldReceive('isUnderMaintenance')
            ->once()
            ->andReturn(false);

        $request = Request::create('/test', 'GET');
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function メンテナンス中は503エラーを返す(): void
    {
        // Arrange
        $maintenanceDto = new Maintenance(
            isMaintenance: true,
            startAt: '2026-08-10 00:00:00',
            endAt: '2026-08-10 03:00:00',
            title: 'メンテナンス実施中',
            message: 'システムメンテナンス中です'
        );

        $this->maintenanceService
            ->shouldReceive('isUnderMaintenance')
            ->once()
            ->andReturn(true);

        $this->maintenanceService
            ->shouldReceive('findMaintenanceInfo')
            ->once()
            ->andReturn($maintenanceDto);

        $request = Request::create('/test', 'GET');
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertFalse($nextCalled);
        $this->assertEquals(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Service Unavailable', $content['error']);
        $this->assertEquals('システムメンテナンス中です', $content['message']);
        $this->assertEquals('メンテナンス実施中', $content['title']);
    }

    #[Test]
    public function 除外_i_pからのアクセスはメンテナンス中でも通す(): void
    {
        // Arrange
        config(['maintenance.excluded_ips' => ['192.168.1.100']]);

        // メンテナンスチェックは実行されない（除外IPのため）
        $this->maintenanceService
            ->shouldReceive('isUnderMaintenance')
            ->never();

        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.100']);
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function 除外ルートはメンテナンス中でもアクセス可能(): void
    {
        // Arrange
        config(['maintenance.excluded_routes' => [
            'auth/version',
            'maintenance/status',
            'admin/maintenance/*',
        ]]);

        // メンテナンスチェックは実行されない（除外ルートのため）
        $this->maintenanceService
            ->shouldReceive('isUnderMaintenance')
            ->never();

        $request = Request::create('/auth/version', 'GET');
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function ワイルドカードパターンの除外ルートが機能する(): void
    {
        // Arrange
        config(['maintenance.excluded_routes' => [
            'admin/maintenance/*',
        ]]);

        // メンテナンスチェックは実行されない（ワイルドカード除外）
        $this->maintenanceService
            ->shouldReceive('isUnderMaintenance')
            ->never();

        $request = Request::create('/admin/maintenance/start', 'POST');
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertTrue($nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function 除外ルート以外はメンテナンス判定が実行される(): void
    {
        // Arrange
        config(['maintenance.excluded_routes' => [
            'auth/version',
            'maintenance/status',
        ]]);

        $maintenanceDto = new Maintenance(
            isMaintenance: true,
            startAt: '2026-08-10 00:00:00',
            endAt: '2026-08-10 03:00:00',
            title: 'メンテナンス実施中',
            message: 'システムメンテナンス中です'
        );

        $this->maintenanceService
            ->shouldReceive('isUnderMaintenance')
            ->once()
            ->andReturn(true);

        $this->maintenanceService
            ->shouldReceive('findMaintenanceInfo')
            ->once()
            ->andReturn($maintenanceDto);

        $request = Request::create('/player/me', 'GET');
        $nextCalled = false;
        $next = function ($req) use (&$nextCalled) {
            $nextCalled = true;

            return response()->json(['success' => true]);
        };

        // Act
        $response = $this->middleware->handle($request, $next);

        // Assert
        $this->assertFalse($nextCalled);
        $this->assertEquals(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
    }
}
