<?php

namespace App\Repositories\Trx;

use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusNotification\Constants\NotificationType;
use NexusNotification\Contracts\NotificationRepositoryInterface;
use NexusNotification\DataTransferObjects\Notification;

/**
 * TrxNotificationRepository
 *
 * trx_notification の永続化。nexus-notification のインターフェースを直接実装する。
 *
 * インターフェースが接続名を受け取らないため、シャードはログイン中プレイヤーの
 * 割り当てから解決する。通知は必ず本人の行しか触らないため、これで足りる。
 *
 * insert() は採番したIDを載せたDTOを返す必要がある（リアルタイム配送が
 * そのIDを使う）ため、UnitOfWorkのキューではなく直接INSERTする。
 */
class TrxNotificationRepository implements NotificationRepositoryInterface
{
    private const TABLE = 'trx_notification';

    /**
     * {@inheritDoc}
     */
    public function selectByPlayerId(int $playerId, bool $onlyUnread = false): array
    {
        $query = DB::connection($this->connection())
            ->table(self::TABLE)
            ->where('sys_player_id', $playerId);

        if ($onlyUnread) {
            $query->where('is_read', false);
        }

        return $query->orderByDesc('id')
            ->get()
            ->map(fn ($row) => $this->toDto((array) $row))
            ->all();
    }

    /**
     * {@inheritDoc}
     */
    public function selectById(int $id): ?Notification
    {
        $row = DB::connection($this->connection())
            ->table(self::TABLE)
            ->where('id', $id)
            ->first();

        return $row ? $this->toDto((array) $row) : null;
    }

    /**
     * {@inheritDoc}
     */
    public function insert(
        int $playerId,
        string $type,
        string $title,
        string $body,
        array $payload = [],
    ): Notification {
        $now = ClockUtility::nowToString();

        $id = DB::connection($this->connection())->table(self::TABLE)->insertGetId([
            'sys_player_id' => $playerId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'payload' => $payload === [] ? null : json_encode($payload),
            'is_read' => false,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new Notification(
            id: $id,
            playerId: $playerId,
            type: NotificationType::from($type),
            title: $title,
            body: $body,
            payload: $payload,
            isRead: false,
            readAt: null,
            createdAt: $now,
        );
    }

    /**
     * {@inheritDoc}
     */
    public function markAsRead(int $id): void
    {
        $now = ClockUtility::nowToString();

        DB::connection($this->connection())
            ->table(self::TABLE)
            ->where('id', $id)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * {@inheritDoc}
     */
    public function markAllAsRead(int $playerId): void
    {
        $now = ClockUtility::nowToString();

        DB::connection($this->connection())
            ->table(self::TABLE)
            ->where('sys_player_id', $playerId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * {@inheritDoc}
     */
    public function countUnread(int $playerId): int
    {
        return DB::connection($this->connection())
            ->table(self::TABLE)
            ->where('sys_player_id', $playerId)
            ->where('is_read', false)
            ->count();
    }

    private function connection(): string
    {
        return ApiSession::resolveConnectionName('trx');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toDto(array $row): Notification
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $row['payload'] === null ? null : json_decode((string) $row['payload'], true);

        return new Notification(
            id: (int) $row['id'],
            playerId: (int) $row['sys_player_id'],
            type: NotificationType::from((string) $row['type']),
            title: (string) $row['title'],
            body: (string) $row['body'],
            payload: $payload ?? [],
            isRead: (bool) $row['is_read'],
            readAt: $row['read_at'] === null ? null : (string) $row['read_at'],
            createdAt: (string) $row['created_at'],
        );
    }
}
