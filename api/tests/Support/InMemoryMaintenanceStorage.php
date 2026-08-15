<?php

namespace Tests\Support;

use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusMaintenance\ValueObjects\Maintenance;

/**
 * テスト用のメンテナンスストレージ
 *
 * 本番のドライバ（DynamoDB / TableStore）は外部SDKと実際のクラウド接続を必要とするため、
 * テストではメモリ上に保持するだけのこの実装に差し替える。
 * 既定ではメンテナンス情報なし＝メンテ中でない状態になる。
 */
class InMemoryMaintenanceStorage implements MaintenanceStorageInterface
{
    private ?Maintenance $maintenance = null;

    public function get(): ?Maintenance
    {
        return $this->maintenance;
    }

    public function put(Maintenance $maintenance): bool
    {
        $this->maintenance = $maintenance;

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
