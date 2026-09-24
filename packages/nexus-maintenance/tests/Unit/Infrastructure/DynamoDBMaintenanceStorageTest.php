<?php

namespace NexusMaintenance\Tests\Unit\Infrastructure;

use Aws\Command;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Aws\Result;
use Nexus\Core\Utilities\ClockUtility;
use NexusMaintenance\Infrastructure\DynamoDB\DynamoDBMaintenanceStorage;
use NexusMaintenance\ValueObjects\Maintenance;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * DynamoDBMaintenanceStorage のテスト
 *
 * AWSへは接続せず、クライアントを差し替えて
 * 送っているリクエストと、返ってきた値の解釈を確認する。
 *
 * 障害時にnull/falseを返すこと（メンテ判定でリクエスト全体を落とさない）も
 * ここで担保する。
 */
class DynamoDBMaintenanceStorageTest extends TestCase
{
    private const CONFIG = [
        'table' => 'maintenance',
        'primary_key' => 'current',
        'region' => 'ap-northeast-1',
        'key' => 'dummy-key',
        'secret' => 'dummy-secret',
    ];

    /** @var list<array{0: string, 1: array<string, mixed>}> 送ったリクエスト */
    private array $calls = [];

    private Result $result;

    private bool $throwOnCall = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calls = [];
        $this->result = new Result([]);
        $this->throwOnCall = false;
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 主キーを指定して1件取得する(): void
    {
        $this->result = new Result([
            'Item' => [
                'is_maintenance' => ['BOOL' => true],
                'start_at' => ['S' => '2026-03-15 02:00:00'],
                'end_at' => ['S' => '2026-03-15 06:00:00'],
                'title' => ['S' => '定期メンテナンス'],
                'message' => ['S' => '停止します'],
                'updated_at' => ['S' => '2026-03-15 01:00:00'],
            ],
        ]);

        $maintenance = $this->makeStorage($this->makeClient())->get();

        $this->assertNotNull($maintenance);
        $this->assertTrue($maintenance->getIsMaintenance());
        $this->assertSame('2026-03-15 02:00:00', $maintenance->getStartAt());
        $this->assertSame('定期メンテナンス', $maintenance->getTitle());

        $this->assertSame('getItem', $this->calls[0][0]);
        $this->assertSame([
            'TableName' => 'maintenance',
            'Key' => ['id' => ['S' => 'current']],
        ], $this->calls[0][1]);
    }

    #[Test]
    public function 空の項目はnullとして扱う(): void
    {
        $this->result = new Result([
            'Item' => [
                'is_maintenance' => ['BOOL' => false],
                'start_at' => ['S' => ''],
                'end_at' => ['S' => ''],
                'title' => ['S' => ''],
                'message' => ['S' => ''],
                'updated_at' => ['S' => ''],
            ],
        ]);

        $maintenance = $this->makeStorage($this->makeClient())->get();

        $this->assertNotNull($maintenance);
        $this->assertFalse($maintenance->getIsMaintenance());
        $this->assertNull($maintenance->getStartAt());
        $this->assertNull($maintenance->getEndAt());
        $this->assertNull($maintenance->getUpdatedAt());
    }

    #[Test]
    public function 項目が無ければnullを返す(): void
    {
        $this->result = new Result([]);

        $this->assertNull($this->makeStorage($this->makeClient())->get());
    }

    #[Test]
    public function 保存では更新日時を現在時刻で埋める(): void
    {
        ClockUtility::setNow('2026-03-15 12:00:00');

        $result = $this->makeStorage($this->makeClient())->put(new Maintenance(
            isMaintenance: true,
            startAt: '2026-03-15 02:00:00',
            endAt: null,
            title: '緊急メンテ',
            message: '調査中',
        ));

        $this->assertTrue($result);
        $this->assertSame('putItem', $this->calls[0][0]);

        $item = $this->calls[0][1]['Item'];
        $this->assertSame(['BOOL' => true], $item['is_maintenance']);
        $this->assertSame(['S' => '2026-03-15 02:00:00'], $item['start_at']);
        // 未設定の項目は空文字で送る
        $this->assertSame(['S' => ''], $item['end_at']);
        $this->assertSame(['S' => '2026-03-15 12:00:00'], $item['updated_at']);
    }

    #[Test]
    public function 削除と疎通確認は主キーとテーブル名だけを送る(): void
    {
        $storage = $this->makeStorage($this->makeClient());

        $this->assertTrue($storage->delete());
        $this->assertTrue($storage->healthCheck());

        $this->assertSame('deleteItem', $this->calls[0][0]);
        $this->assertSame(['id' => ['S' => 'current']], $this->calls[0][1]['Key']);
        $this->assertSame('describeTable', $this->calls[1][0]);
        $this->assertSame(['TableName' => 'maintenance'], $this->calls[1][1]);
    }

    #[Test]
    public function 通信に失敗しても例外を投げずに失敗を返す(): void
    {
        $this->throwOnCall = true;

        $storage = $this->makeStorage($this->makeClient());

        $this->assertNull($storage->get());
        $this->assertFalse($storage->put(new Maintenance(isMaintenance: true)));
        $this->assertFalse($storage->delete());
        $this->assertFalse($storage->healthCheck());
    }

    /**
     * 呼び出しを記録するだけのクライアントを作る
     *
     * getItem等はSDKでは__call経由の動的メソッドなので、そこを差し替える。
     */
    private function makeClient(): DynamoDbClient
    {
        $client = $this->createMock(DynamoDbClient::class);

        $client->method('__call')->willReturnCallback(function (string $name, array $args) {
            $this->calls[] = [$name, $args[0] ?? []];

            if ($this->throwOnCall) {
                throw new DynamoDbException('connection failed', new Command($name));
            }

            return $this->result;
        });

        return $client;
    }

    private function makeStorage(DynamoDbClient $client): DynamoDBMaintenanceStorage
    {
        return new DynamoDBMaintenanceStorage(self::CONFIG, $client);
    }
}
