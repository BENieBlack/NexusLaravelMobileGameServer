<?php

namespace Tests\Support;

use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusMaintenance\DTOs\MaintenanceDto;

/**
 * テスト用のメンテナンスストレージ
 *
 * 本番のドライバ（DynamoDB / TableStore）は外部SDKと実際のクラウド接続を必要とするため、
 * テストではメモリ上に保持するだけのこの実装に差し替える。
 * 既定ではメンテナンス情報なし＝メンテ中でない状態になる。
 */
class InMemoryMaintenanceStorage implements MaintenanceStorageInterface
{
    private ?MaintenanceDto $maintenance = null;

    public function get(): ?MaintenanceDto
    {
        return $this->maintenance;
    }

    public function put(MaintenanceDto $maintenanceDto): bool
    {
        $this->maintenance = $maintenanceDto;

        return true;
    }

    public function delete(): bool
    {
        $this->maintenance = null;

        return true;
    }

    public function healthCheck(): bool
    {
        return true;
    }
}
