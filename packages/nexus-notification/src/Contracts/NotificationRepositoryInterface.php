<?php

namespace NexusNotification\Contracts;

use NexusNotification\DataTransferObjects\Notification;

/**
 * NotificationRepositoryInterface
 *
 * 通知の永続化インターフェース
 * Application層のRepositoryAdapterが実装する
 */
interface NotificationRepositoryInterface
{
    /**
     * プレイヤーIDで通知一覧を取得
     *
     * @param  int  $playerId  プレイヤーID
     * @param  bool  $onlyUnread  未読のみ取得するか
     * @return array<Notification>
     */
    public function selectByPlayerId(int $playerId, bool $onlyUnread = false): array;

    /**
     * IDで通知を取得
     *
     * @param  int  $id  通知ID
     */
    public function selectById(int $id): ?Notification;

    /**
     * 通知を作成
     *
     * @param  int  $playerId  送信先プレイヤーID
     * @param  string  $type  通知種別 (NotificationType::value)
     * @param  string  $title  タイトル
     * @param  string  $body  本文
     * @param  array<string, mixed>  $payload  追加データ
     */
    public function insert(
        int $playerId,
        string $type,
        string $title,
        string $body,
        array $payload = [],
    ): Notification;

    /**
     * 通知を既読にする
     *
     * @param  int  $id  通知ID
     */
    public function markAsRead(int $id): void;

    /**
     * プレイヤーの全通知を既読にする
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function markAllAsRead(int $playerId): void;

    /**
     * 未読通知数を取得
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function countUnread(int $playerId): int;
}
