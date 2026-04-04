<?php

namespace Tests\Feature\Repositories\Trx;

use App\Repositories\Log\LogEquipmentRepository;
use App\Repositories\Trx\TrxEquipmentRepository;
use App\Persistence\ApiSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
    public function queryOrMemoryは引数なしで動作する(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxEquipmentRepository();
        
        $equipments = $repo->queryOrMemory();
        
        $this->assertNotNull($equipments);
    }

    #[Test]
    public function ユニークキーがプロパティとして定義されている(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        $repo = new TrxEquipmentRepository();
        
        $reflection = new \ReflectionClass($repo);
        $property = $reflection->getProperty('uniqueKeys');
        $property->setAccessible(true);
        $uniqueKeys = $property->getValue($repo);
        
        $this->assertIsArray($uniqueKeys);
        $this->assertContains('id', $uniqueKeys);
    }

    #[Test]
    public function 異なるsysPlayerIdで別のリポジトリを作成できる(): void
    {
        ApiSession::setSysPlayerId(999);
        $repo = new TrxEquipmentRepository();
        
        $equipments = $repo->queryOrMemory();
        
        $this->assertSame(0, $equipments->count());
    }

    #[Test]
    public function ApiSessionを設定してTrxRepositoryで使用できる(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        
        $trxEquipmentRepo = new TrxEquipmentRepository();
        $equipments = $trxEquipmentRepo->queryOrMemory();
        
        $this->assertNotNull($equipments);
    }

    #[Test]
    public function LogRepositoryでもApiSessionが使用できる(): void
    {
        ApiSession::setSysPlayerId($this->sysPlayerId);
        
        $logEquipmentRepo = new LogEquipmentRepository();
        $logs = $logEquipmentRepo->queryOrMemory();
        
        $this->assertNotNull($logs);
    }

    #[Test]
    public function プレイヤーID未設定時にqueryOrMemoryで例外がスローされる(): void
    {
        $this->expectException(\RuntimeException::class);
        
        $trxEquipmentRepo = new TrxEquipmentRepository();
        $trxEquipmentRepo->queryOrMemory();
    }
}
