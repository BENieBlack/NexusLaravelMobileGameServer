<?php

namespace Tests\Feature\Repositories\Trx;

use App\Persistence\ApiSession;
use App\Repositories\Log\LogEquipmentRepository;
use App\Repositories\Trx\TrxEquipmentRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Repository層のApiSession統合テスト
 *
 * RepositoryがApiSessionからプレイヤーIDを自動取得できることを確認
 */
class RepositoryApiSessionTest extends TestCase
{
    use RefreshDatabase;

    private int $sysPlayerId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        ApiSession::clearForTest();
    }

    protected function tearDown(): void
    {
        ApiSession::clearForTest();
        parent::tearDown();
    }

    #[Test]
    public function query_or_memoryは引数なしで動作する(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxEquipmentRepository;

        $equipments = $repo->queryOrMemory();

        $this->assertNotNull($equipments);
    }

    #[Test]
    public function ユニークキーがプロパティとして定義されている(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxEquipmentRepository;

        $reflection = new \ReflectionClass($repo);
        $property = $reflection->getProperty('uniqueKeys');
        $property->setAccessible(true);
        $uniqueKeys = $property->getValue($repo);

        $this->assertIsArray($uniqueKeys);
        $this->assertContains('id', $uniqueKeys);
    }

    #[Test]
    public function 異なるsys_player_idで別のリポジトリを作成できる(): void
    {
        ApiSession::setSysPlayerId(999);
        $repo = new TrxEquipmentRepository;

        $equipments = $repo->queryOrMemory();

        $this->assertSame(0, $equipments->count());
    }

    #[Test]
    public function api_sessionを設定して_trx_repositoryで使用できる(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $trxEquipmentRepo = new TrxEquipmentRepository;
        $equipments = $trxEquipmentRepo->queryOrMemory();

        $this->assertNotNull($equipments);
    }

    #[Test]
    public function log_repositoryでも_api_sessionが使用できる(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);

        $logEquipmentRepo = new LogEquipmentRepository;
        $logs = $logEquipmentRepo->queryOrMemory();

        $this->assertNotNull($logs);
    }

    #[Test]
    public function プレイヤー_i_d未設定時にquery_or_memoryで例外がスローされる(): void
    {
        $this->expectException(\RuntimeException::class);

        $trxEquipmentRepo = new TrxEquipmentRepository;
        $trxEquipmentRepo->queryOrMemory();
    }
}
