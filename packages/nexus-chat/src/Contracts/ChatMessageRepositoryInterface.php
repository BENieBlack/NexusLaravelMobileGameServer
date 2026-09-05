<?php

namespace NexusChat\Contracts;

use NexusChat\DataTransferObjects\ChatMessage;

/**
 * ChatMessageRepositoryInterface
 *
 * チャットメッセージの永続化インターフェース
 * Application層のRepositoryAdapterが実装する
 */
interface ChatMessageRepositoryInterface
{
    /**
     * チャットルームのメッセージ一覧を取得（カーソルページネーション）
     *
     * @param  int  $chatRoomId  チャットルームID
     * @param  int  $limit  取得件数
     * @param  int|null  $beforeMessageId  このID以前のメッセージを取得（NULL=最新から）
     * @return array<ChatMessage>
     */
    public function selectByRoomId(int $chatRoomId, int $limit = 30, ?int $beforeMessageId = null): array;

    /**
     * IDでメッセージを取得
     *
     * @param  int  $messageId  メッセージID
     */
    public function selectById(int $messageId): ?ChatMessage;

    /**
     * メッセージを作成
     *
     * @param  int  $chatRoomId  チャットルームID
     * @param  int  $senderPlayerId  送信者プレイヤーID
     * @param  string  $senderName  送信者表示名（非正規化して保存）
     * @param  string  $body  本文
     */
    public function insert(
        int $chatRoomId,
        int $senderPlayerId,
        string $senderName,
        string $body,
    ): ChatMessage;

    /**
     * メッセージを論理削除（送信者本人または管理者のみ）
     *
     * @param  int  $messageId  メッセージID
     */
    public function softDelete(int $messageId): void;
}
