<?php

namespace NexusMaintenance\Infrastructure\DynamoDB;

use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusMaintenance\DTOs\MaintenanceDto;
use NexusUtilities\ClockUtility;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;
use Illuminate\Support\Facades\Log;

/**
 * DynamoDB メンテナンスストレージ実装
 * 
 * AWS DynamoDBを使用したメンテナンス情報の永続化
 */
class DynamoDBMaintenanceStorage implements MaintenanceStorageInterface
{
    private DynamoDbClient $client;
    private string $tableName;
    private string $primaryKey;

    public function __construct(array $config)
    {
        $this->tableName = $config['table'];
        $this->primaryKey = $config['primary_key'];
        
        $clientConfig = [
            'version' => 'latest',
            'region' => $config['region'],
            'credentials' => [
                'key' => $config['key'],
                'secret' => $config['secret'],
            ],
        ];

        // ローカル開発用エンドポイント設定
        if (!empty($config['endpoint'])) {
            $clientConfig['endpoint'] = $config['endpoint'];
        }

        $this->client = new DynamoDbClient($clientConfig);
    }

    /**
     * {@inheritDoc}
     */
    public function get(): ?MaintenanceDto
    {
        try {
            $result = $this->client->getItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'id' => ['S' => $this->primaryKey],
                ],
            ]);

            if (!isset($result['Item'])) {
                return null;
            }

            return $this->parseItem($result['Item']);
        } catch (DynamoDbException $e) {
            Log::error('DynamoDB get error', [
                'error' => $e->getMessage(),
                'table' => $this->tableName,
            ]);
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function put(MaintenanceDto $sysMaintenance): bool
    {
        try {
            $this->client->putItem([
                'TableName' => $this->tableName,
                'Item' => [
                    'id' => ['S' => $this->primaryKey],
                    'is_maintenance' => ['BOOL' => $sysMaintenance->isMaintenance],
                    'start_at' => ['S' => $sysMaintenance->startAt ?? ''],
                    'end_at' => ['S' => $sysMaintenance->endAt ?? ''],
                    'title' => ['S' => $sysMaintenance->title ?? ''],
                    'message' => ['S' => $sysMaintenance->message ?? ''],
                    'updated_at' => ['S' => ClockUtility::nowToString()],
                ],
            ]);

            return true;
        } catch (DynamoDbException $e) {
            Log::error('DynamoDB put error', [
                'error' => $e->getMessage(),
                'table' => $this->tableName,
            ]);
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(): bool
    {
        try {
            $this->client->deleteItem([
                'TableName' => $this->tableName,
                'Key' => [
                    'id' => ['S' => $this->primaryKey],
                ],
            ]);

            return true;
        } catch (DynamoDbException $e) {
            Log::error('DynamoDB delete error', [
                'error' => $e->getMessage(),
                'table' => $this->tableName,
            ]);
            return false;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function healthCheck(): bool
    {
        try {
            $this->client->describeTable([
                'TableName' => $this->tableName,
            ]);
            return true;
        } catch (DynamoDbException $e) {
            Log::error('DynamoDB health check failed', [
                'error' => $e->getMessage(),
                'table' => $this->tableName,
            ]);
            return false;
        }
    }

    /**
     * DynamoDBアイテムをSysMaintenanceに変換
     */
    private function parseItem(array $item): MaintenanceDto
    {
        return new MaintenanceDto(
            isMaintenance: $item['is_maintenance']['BOOL'] ?? false,
            startAt: !empty($item['start_at']['S']) ? $item['start_at']['S'] : null,
            endAt: !empty($item['end_at']['S']) ? $item['end_at']['S'] : null,
            title: $item['title']['S'] ?? null,
            message: $item['message']['S'] ?? null,
            updatedAt: !empty($item['updated_at']['S']) ? $item['updated_at']['S'] : null,
        );
    }
}
