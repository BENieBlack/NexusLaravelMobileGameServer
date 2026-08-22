<?php

namespace NexusMaintenance\Infrastructure\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Nexus\Core\Utilities\ClockUtility;
use NexusMaintenance\Contracts\MaintenanceStorageInterface;
use NexusMaintenance\ValueObjects\Maintenance;

/**
 * RDBを使ったメンテナンス情報ストレージ
 *
 * DynamoDB / TableStore は外部SDKとクラウド接続を必要とするため、
 * ローカル開発やクラウド非依存の構成ではこの実装を使う。
 * 既存の sys_maintenance テーブルをそのまま利用する。
 *
 * 有効なメンテナンスは高々1件という前提で扱い、
 * put() の際に既存の有効レコードを無効化してから登録する。
 */
class DatabaseMaintenanceStorage implements MaintenanceStorageInterface
{
    private string $connection;

    private string $table;

    /**
     * @param  array{connection?: string, table?: string}  $config
     */
    public function __construct(array $config = [])
    {
        $this->connection = $config['connection'] ?? 'sys';
        $this->table = $config['table'] ?? 'sys_maintenance';
    }

    /**
     * メンテナンス情報を取得
     *
     * 有効なレコードが存在しない場合はnullを返す
     */
    public function get(): ?Maintenance
    {
        try {
            $row = $this->query()
                ->where('is_active', true)
                ->orderByDesc('start_at')
                ->orderByDesc('id')
                ->first();

            if ($row === null) {
                return null;
            }

            return new Maintenance(
                isMaintenance: (bool) $row->is_active,
                startAt: $this->toDateTimeString($row->start_at),
                endAt: $this->toDateTimeString($row->end_at),
                title: $row->title,
                message: $row->message,
                updatedAt: $this->toDateTimeString($row->updated_at),
            );
        } catch (\Throwable $e) {
            Log::error('Failed to get maintenance from database', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * メンテナンス情報を保存
     *
     * 既存の有効なレコードは無効化してから新しいレコードを登録する
     */
    public function put(Maintenance $maintenance): bool
    {
        try {
            $now = ClockUtility::nowToString();

            DB::connection($this->connection)->transaction(function () use ($maintenance, $now) {
                $this->query()
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                $this->query()->insert([
                    'title' => $maintenance->getTitle() ?? '',
                    'message' => $maintenance->getMessage() ?? '',
                    'start_at' => $maintenance->getStartAt() ?? $now,
                    'end_at' => $maintenance->getEndAt(),
                    'is_active' => $maintenance->getIsMaintenance(),
                ]);
            });

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to put maintenance into database', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * メンテナンス情報を削除（メンテ終了）
     *
     * 履歴を残すため物理削除ではなく無効化する
     */
    public function delete(): bool
    {
        try {
            $this->query()
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('Failed to delete maintenance from database', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * ストレージの接続確認
     */
    public function healthCheck(): bool
    {
        try {
            $this->query()->limit(1)->exists();

            return true;
        } catch (\Throwable $e) {
            Log::error('Maintenance storage health check failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * クエリビルダを取得
     */
    private function query(): \Illuminate\Database\Query\Builder
    {
        return DB::connection($this->connection)->table($this->table);
    }

    /**
     * 日時をY-m-d H:i:s形式の文字列に正規化
     */
    private function toDateTimeString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return ClockUtility::parse((string) $value)->format('Y-m-d H:i:s');
    }
}
