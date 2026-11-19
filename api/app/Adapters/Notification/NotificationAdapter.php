<?php

namespace App\Adapters\Notification;

use NexusNotification\Constants\NotificationType;
use NexusNotification\DataTransferObjects\Notification;

/**
 * NotificationAdapter
 *
 * trx_notification 1 行を DTO（Notification）へ変換するアダプター。
 * 対応する Model を持たないテーブルをパッケージ契約の Repository（*RepositoryAdapter）が
 * DB::table で読むため、入力は Model ではなく行の連想配列。変換は Repository 側に書かずここへ集める（ConverterLocationTest）。
 */
class NotificationAdapter
{
    /**
     * DB の行（連想配列）から DTO に変換する
     *
     * @param  array<string, mixed>  $row
     */
    public static function toDto(array $row): Notification
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
