<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\CheckMasterHash;
use App\Models\Sys\SysDeploy;
use App\Models\Sys\SysDeployMaster;
use App\Models\Sys\SysDeployMasterTable;
use App\Repositories\Sys\SysDeployRepository;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CheckMasterHashミドルウェアのテスト
 */
class CheckMasterHashTest extends TestCase
{
    private SysDeployRepository $sysDeployRepository;

    private CheckMasterHash $middleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sysDeployRepository = Mockery::mock(SysDeployRepository::class);
        $this->middleware = new CheckMasterHash($this->sysDeployRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function ハッシュが一致する場合は更新通知を付けない(): void
    {
        $this->sysDeployRepository
            ->shouldReceive('selectLatestDownloadable')
            ->once()
            ->andReturn($this->makeDeploy('master-hash'));

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Master-Hash', 'master-hash');

        $response = $this->middleware->handle($request, fn () => response()->json(['success' => true]));

        $this->assertSame(['success' => true], $response->getData(true));
    }

    #[Test]
    public function ハッシュが不一致の場合は更新要求ヘッダーを付ける(): void
    {
        $this->sysDeployRepository
            ->shouldReceive('selectLatestDownloadable')
            ->once()
            ->andReturn($this->makeDeploy('latest-hash'));

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Master-Hash', 'old-hash');

        $response = $this->middleware->handle($request, fn () => response()->json(['success' => true]));
        $this->assertSame(['success' => true], $response->getData(true));
        $this->assertSame('true', $response->headers->get('X-Master-Update-Required'));
    }

    #[Test]
    public function ヘッダー未指定時はレスポンスを変更しない(): void
    {
        $this->sysDeployRepository->shouldReceive('selectLatestDownloadable')->never();

        $request = Request::create('/test', 'GET');
        $response = $this->middleware->handle($request, fn () => response()->json(['success' => true]));

        $this->assertSame(['success' => true], $response->getData(true));
    }

    #[Test]
    public function JSON以外のレスポンスにも更新要求ヘッダーを付ける(): void
    {
        $this->sysDeployRepository
            ->shouldReceive('selectLatestDownloadable')
            ->once()
            ->andReturn($this->makeDeploy('latest-hash'));

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Master-Hash', 'old-hash');
        $response = $this->middleware->handle($request, function () {
            return response('ok', 200);
        });

        $this->assertSame('ok', $response->getContent());
        $this->assertSame('true', $response->headers->get('X-Master-Update-Required'));
    }

    #[Test]
    public function マスター確認に失敗しても元のレスポンスを返す(): void
    {
        $this->sysDeployRepository
            ->shouldReceive('selectLatestDownloadable')
            ->once()
            ->andThrow(new \RuntimeException('database unavailable'));

        $request = Request::create('/test', 'GET');
        $request->headers->set('X-Master-Hash', 'old-hash');

        $response = $this->middleware->handle($request, fn () => response()->json(['success' => true]));

        $this->assertSame(['success' => true], $response->getData(true));
    }

    private function makeDeploy(string $hash): SysDeploy
    {
        $table = new SysDeployMasterTable([
            'table_name' => 'mst_unit',
            'hash' => 'unit-hash',
            'public_url' => '/masterdata/units.sqlite',
            'file_size' => 2048,
        ]);
        $master = new SysDeployMaster(['hash' => $hash]);
        $master->setRelation('tables', collect([$table]));

        $sysDeploy = new SysDeploy(['sys_deploy_master_id' => 7]);
        $sysDeploy->setRelation('deployMaster', $master);

        return $sysDeploy;
    }
}
