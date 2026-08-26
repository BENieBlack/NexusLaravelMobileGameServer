<?php

namespace NexusMaintenance\Infrastructure\TableStore;

use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusMaintenance\ValueObjects\Maintenance;
use Nexus\Core\Utilities\ClockUtility;
use Aliyun\OTS\OTSClient;
use Aliyun\OTS\Consts\PrimaryKeyTypeConst;
use Aliyun\OTS\Consts\RowExistenceExpectationConst;
use Illuminate\Support\Facades\Log;

/**
 * Alibaba Cloud TableStore メンテナンスストレージ実装
 * 
 * Alibaba Cloud TableStoreを使用したメンテナンス情報の永続化
 */
class TableStoreMaintenanceStorage implements MaintenanceStorageInterface
{
    private OTSClient $client;
    private string $tableName;
    private string $primaryKey;

    /**
     * @param array<string, mixed> $config
     * @param OTSClient|null $client 生成済みのクライアント（テストや差し替え用）
     */
    public function __construct(array $config, ?OTSClient $client = null)
    {
        $this->tableName = $config['table'];
        $this->primaryKey = $config['primary_key'];

        $this->client = $client ?? new OTSClient([
            'EndPoint' => $config['endpoint'],
            'AccessKeyID' => $config['access_key_id'],
            'AccessKeySecret' => $config['access_key_secret'],
            'InstanceName' => $config['instance'],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function get(): ?Maintenance
    {
        try {
            $response = $this->client->getRow([
                'table_name' => $this->tableName,
                'primary_key' => [
                    ['id', $this->primaryKey],
                ],
            ]);

            if (empty($response['attribute_columns'])) {
                return null;
            }

            return $this->parseRow($response['attribute_columns']);
        } catch (\Exception $e) {
            Log::error('TableStore get error', [
                'error' => $e->getMessage(),
                'table' => $this->tableName,
            ]);
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function put(Maintenance $maintenance): bool
    {
        try {
            $this->client->putRow([
                'table_name' => $this->tableName,
                'condition' => RowExistenceExpectationConst::CONST_IGNORE,
                'primary_key' => [
                    ['id', $this->primaryKey],
                ],
                'attribute_columns' => [
                    ['is_maintenance', $maintenance->getIsMaintenance()],
                    ['start_at', $maintenance->getStartAt() ?? ''],
                    ['end_at', $maintenance->getEndAt() ?? ''],
                    ['title', $maintenance->getTitle() ?? ''],
                    ['message', $maintenance->getMessage() ?? ''],
                    ['updated_at', ClockUtility::nowToString()],
                ],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('TableStore put error', [
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
            $this->client->deleteRow([
                'table_name' => $this->tableName,
                'condition' => RowExistenceExpectationConst::CONST_IGNORE,
                'primary_key' => [
                    ['id', $this->primaryKey],
                ],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('TableStore delete error', [
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
                'table_name' => $this->tableName,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::error('TableStore health check failed', [
                'error' => $e->getMessage(),
                'table' => $this->tableName,
            ]);
            return false;
        }
    }

    /**
     * TableStore行データをSysMaintenanceに変換
     *
     * @param array<int, array{0: string, 1: mixed}> $columns [カラム名, 値] の配列
     */
    private function parseRow(array $columns): Maintenance
    {
        $data = [];
        foreach ($columns as $column) {
            $data[$column[0]] = $column[1];
        }

        return new Maintenance(
            isMaintenance: $data['is_maintenance'] ?? false,
            startAt: !empty($data['start_at']) ? $data['start_at'] : null,
            endAt: !empty($data['end_at']) ? $data['end_at'] : null,
            title: $data['title'] ?? null,
            message: $data['message'] ?? null,
            updatedAt: !empty($data['updated_at']) ? $data['updated_at'] : null,
        );
    }
}
