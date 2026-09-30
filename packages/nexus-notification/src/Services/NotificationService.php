<?php

namespace NexusNotification\Services;

use NexusNotification\Constants\NotificationType;
use NexusNotification\Contracts\NotificationDispatcherInterface;
use NexusNotification\Contracts\NotificationRepositoryInterface;
use NexusNotification\DataTransferObjects\Notification;
use NexusNotification\Events\NotificationCreated;

/**
 * NotificationService
 *
 * ゲーム内通知の配送インフラサービス
 *
 * 他パッケージ（nexus-friend, nexus-mailbox, nexus-missionなど）が
 * 通知を送りたい場合はこのサービスを呼び出す。
 * 永続化とリアルタイム配送を一括で処理する。
 *
 * 使用例:
 *   $this->notificationService->notify(
 *       playerId: $receiverId,
 *       type: NotificationType::FRIEND_APPLY_RECEIVED,
 *       title: 'フレンド申請が届きました',
 *       body: "{$senderName}さんからフレンド申請が届きました",
 *       payload: ['friend_apply_id' => $applyId],
 *   );
 */
class NotificationService
{
    public function __construct(
        private readonly NotificationRepositoryInterface $repository,
        private readonly ?NotificationDispatcherInterface $dispatcher = null,
    ) {}

    /**
     * プレイヤーへ通知を送信する
     *
     * DBへの永続化とリアルタイム配送（Dispatcher設定時）を行う
     *
     * @param  int  $playerId  送信先プレイヤーID
     * @param  NotificationType  $type  通知種別
     * @param  string  $title  通知タイトル
     * @param  string  $body  通知本文
     * @param  array<string, mixed>  $payload  追加データ（ID等）
     */
    public function notify(
        int $playerId,
        NotificationType $type,
        string $title,
        string $body,
        array $payload = [],
    ): Notification {
        $notification = $this->repository->insert(
            playerId: $playerId,
            type: $type->value,
            title: $title,
            body: $body,
            payload: $payload,
        );

        // リアルタイム配送（Reverbなど）が設定されていれば配送する
        $this->dispatcher?->dispatch($notification);

        // ドメインイベント発火（Laravelのイベントシステムへ委譲）
        // Application層がListenerを登録して追加処理（FCMプッシュ等）を行う
        event(new NotificationCreated($notification));

        return $notification;
    }

    /**
     * プレイヤーの通知一覧を取得
     *
     * @param  int  $playerId  プレイヤーID
     * @param  bool  $onlyUnread  未読のみ取得するか
     * @return array<Notification>
     */
    public function findByPlayer(int $playerId, bool $onlyUnread = false): array
    {
        return $this->repository->selectByPlayerId($playerId, $onlyUnread);
    }

    /**
     * 通知を既読にする
     *
     * @param  int  $notificationId  通知ID
     * @param  int  $playerId  リクエスト者のプレイヤーID（権限確認）
     */
    public function markAsRead(int $notificationId, int $playerId): void
    {
        $notification = $this->repository->selectById($notificationId);

        if ($notification === null || $notification->getPlayerId() !== $playerId) {
            return;
        }

        $this->repository->markAsRead($notificationId);
    }

    /**
     * 全通知を既読にする
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function markAllAsRead(int $playerId): void
    {
        $this->repository->markAllAsRead($playerId);
    }

    /**
     * 未読通知数を取得
     *
     * @param  int  $playerId  プレイヤーID
     */
    public function countUnread(int $playerId): int
    {
        return $this->repository->countUnread($playerId);
    }
}
