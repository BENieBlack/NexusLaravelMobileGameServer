<?php

namespace NexusMailbox\Repositories;

use NexusMailbox\Dto\MailboxDto;
use NexusMailbox\Constants\Category;
use NexusMailbox\Constants\Priority;

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
     * @param int $sysPlayerId
     * @param Category|null $category
     * @param Priority|null $priority
     * @param bool $onlyUnread
     * @param bool $onlyLocked
     * @return \Illuminate\Support\Collection<MailboxDto>
     */
    public function findByPlayerId(
        int $sysPlayerId,
        ?Category $category = null,
        ?Priority $priority = null,
        bool $onlyUnread = false,
        bool $onlyLocked = false
    ): \Illuminate\Support\Collection;

    /**
     * IDでメールボックスを取得
     * 
     * @param int $id
     * @return MailboxDto|null
     */
    public function findById(int $id): ?MailboxDto;

    /**
     * メールボックスを保存
     * 
     * @param MailboxDto $mailboxDto
     * @return void
     */
    public function save(MailboxDto $mailboxDto): void;

    /**
     * カテゴリ別未読数を取得
     * 
     * @param int $sysPlayerId
     * @return array<string, int>
     */
    public function countUnreadByCategory(int $sysPlayerId): array;

    /**
     * 既読にマーク
     * 
     * @param MailboxDto $mailboxDto
     * @return void
     */
    public function markAsRead(MailboxDto $mailboxDto): void;

    /**
     * 受取済みにマーク（DTO版）
     * 
     * @param MailboxDto $mailboxDto
     * @return void
     */
    public function markDtoAsReceived(MailboxDto $mailboxDto): void;

    /**
     * ロック状態を変更
     * 
     * @param MailboxDto $mailboxDto
     * @param bool $isLocked
     * @return void
     */
    public function updateLockStatus(MailboxDto $mailboxDto, bool $isLocked): void;
}
