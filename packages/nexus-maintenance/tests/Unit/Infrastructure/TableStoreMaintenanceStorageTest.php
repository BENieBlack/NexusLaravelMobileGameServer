<?php

namespace NexusMaintenance\Tests\Unit\Infrastructure;

use Aliyun\OTS\OTSClient;
use Nexus\Core\Utilities\ClockUtility;
use NexusMaintenance\Infrastructure\TableStore\TableStoreMaintenanceStorage;
use NexusMaintenance\ValueObjects\Maintenance;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TableStoreMaintenanceStorage のテスト
 *
 * Alibaba Cloudへは接続せず、クライアントを差し替えて
 * 送っているリクエストと、返ってきた行の解釈を確認する。
 */
class TableStoreMaintenanceStorageTest extends TestCase
{
    private const CONFIG = [
        'table' => 'maintenance',
        'primary_key' => 'current',
        'endpoint' => 'https://example.ap-northeast-1.ots.aliyuncs.com',
        'access_key_id' => 'dummy-key',
        'access_key_secret' => 'dummy-secret',
        'instance' => 'dummy-instance',
    ];

    /** @var list<array{0: string, 1: array<string, mixed>}> 送ったリクエスト */
    private array $calls = [];

    /** @var array<string, mixed> */
    private array $response = [];

    private bool $throwOnCall = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calls = [];
        $this->response = [];
        $this->throwOnCall = false;
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 主キーを指定して1行取得する(): void
    {
        $this->response = [
            'attribute_columns' => [
                ['is_maintenance', true],
                ['start_at', '2026-03-15 02:00:00'],
                ['end_at', '2026-03-15 06:00:00'],
                ['title', '定期メンテナンス'],
                ['message', '停止します'],
                ['updated_at', '2026-03-15 01:00:00'],
            ],
        ];

        $maintenance = $this->makeStorage()->get();

        $this->assertNotNull($maintenance);
        $this->assertTrue($maintenance->getIsMaintenance());
        $this->assertSame('2026-03-15 02:00:00', $maintenance->getStartAt());
        $this->assertSame('定期メンテナンス', $maintenance->getTitle());

        $this->assertSame('getRow', $this->calls[0][0]);
        $this->assertSame([
            'table_name' => 'maintenance',
            'primary_key' => [['id', 'current']],
        ], $this->calls[0][1]);
    }

    #[Test]
    public function 行が無ければnullを返す(): void
    {
        $this->response = ['attribute_columns' => []];

        $this->assertNull($this->makeStorage()->get());
    }

    #[Test]
    public function 空の項目はnullとして扱う(): void
    {
        $this->response = [
            'attribute_columns' => [
                ['is_maintenance', false],
                ['start_at', ''],
                ['end_at', ''],
                ['title', ''],
                ['message', ''],
                ['updated_at', ''],
            ],
        ];

        $maintenance = $this->makeStorage()->get();

        $this->assertNotNull($maintenance);
        $this->assertFalse($maintenance->getIsMaintenance());
        $this->assertNull($maintenance->getStartAt());
        $this->assertNull($maintenance->getUpdatedAt());
    }

    #[Test]
    public function 保存では更新日時を現在時刻で埋める(): void
    {
        ClockUtility::setNow('2026-03-15 12:00:00');

        $result = $this->makeStorage()->put(new Maintenance(
            isMaintenance: true,
            startAt: '2026-03-15 02:00:00',
            endAt: null,
            title: '緊急メンテ',
            message: '調査中',
        ));

        $this->assertTrue($result);
        $this->assertSame('putRow', $this->calls[0][0]);

        $columns = collect($this->calls[0][1]['attribute_columns'])
            ->mapWithKeys(fn (array $column) => [$column[0] => $column[1]])
            ->all();

        $this->assertTrue($columns['is_maintenance']);
        $this->assertSame('2026-03-15 02:00:00', $columns['start_at']);
        // 未設定の項目は空文字で送る
        $this->assertSame('', $columns['end_at']);
        $this->assertSame('2026-03-15 12:00:00', $columns['updated_at']);
    }

    #[Test]
    public function 削除と疎通確認はテーブル名と主キーだけを送る(): void
    {
        $storage = $this->makeStorage();

        $this->assertTrue($storage->delete());
        $this->assertTrue($storage->healthCheck());

        $this->assertSame('deleteRow', $this->calls[0][0]);
        $this->assertSame([['id', 'current']], $this->calls[0][1]['primary_key']);
        $this->assertSame('describeTable', $this->calls[1][0]);
        $this->assertSame(['table_name' => 'maintenance'], $this->calls[1][1]);
    }

    #[Test]
    public function 通信に失敗しても例外を投げずに失敗を返す(): void
    {
        $this->throwOnCall = true;

        $storage = $this->makeStorage();

        $this->assertNull($storage->get());
        $this->assertFalse($storage->put(new Maintenance(isMaintenance: true)));
        $this->assertFalse($storage->delete());
        $this->assertFalse($storage->healthCheck());
    }

    private function makeStorage(): TableStoreMaintenanceStorage
    {
        return new TableStoreMaintenanceStorage(self::CONFIG, $this->makeClient());
    }

    /**
     * 呼び出しを記録するだけのクライアントを作る
     */
    private function makeClient(): OTSClient
    {
        $client = $this->createMock(OTSClient::class);

        foreach (['getRow', 'putRow', 'deleteRow', 'describeTable'] as $method) {
            $client->method($method)->willReturnCallback(function (array $request) use ($method) {
                $this->calls[] = [$method, $request];

                if ($this->throwOnCall) {
                    throw new \RuntimeException('connection failed');
                }

                return $this->response;
            });
        }

        return $client;
    }
}
