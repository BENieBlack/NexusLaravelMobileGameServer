<?php

namespace NexusMailbox\Repositories;

use Illuminate\Support\Collection;
use NexusMailbox\Constants\Category;
use NexusMailbox\Constants\Priority;
use NexusMailbox\DataTransferObjects\Mailbox;

/**
 * MailboxRepositoryInterface
 *
 * メールボックスデータへのアクセスを抽象化
 */
interface MailboxRepositoryInterface
{
    /**
     * プレイヤーIDでメールボックス一覧を取得
     *
     * @return Collection<Mailbox>
     */
    public function selectByPlayerId(
        int $sysPlayerId,
        ?Category $category = null,
        ?Priority $priority = null,
        bool $onlyUnread = false,
        bool $onlyLocked = false
    ): Collection;

    /**
     * IDでメールボックスを取得
     */
    public function selectById(int $id): ?Mailbox;

    /**
     * メールボックスを保存
     */
    public function persist(Mailbox $mailbox): void;

    /**
     * カテゴリ別未読数を取得
     *
     * @return array<string, int>
     */
    public function countUnreadByCategory(int $sysPlayerId): array;

    /**
     * 既読にマーク
     */
    public function markAsRead(Mailbox $mailbox): void;

    /**
     * 受取済みにマーク（DTO版）
     */
    public function markDtoAsReceived(Mailbox $mailbox): void;

    /**
     * ロック状態を変更
     */
    public function updateLockStatus(Mailbox $mailbox, bool $isLocked): void;
}
