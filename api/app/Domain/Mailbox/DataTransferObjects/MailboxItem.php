<?php

namespace App\Domain\Mailbox\DataTransferObjects;

use App\Domain\Mailbox\Constants\Category;
use App\Domain\Mailbox\Constants\Priority;
use App\Domain\Mailbox\Constants\SenderType;

/**
 * メール1件の表示内容（タイトル・本文はテンプレートをレンダリング済み）
 */
class MailboxItem
{
    /**
     * @param  list<MailboxItemContent>  $contents  添付された報酬
     */
    public function __construct(
        public readonly int $trxMailboxId,
        public readonly string $mstMailboxId,
        public readonly string $title,
        public readonly string $body,
        public readonly ?Category $category,
        public readonly ?Priority $priority,
        public readonly ?SenderType $senderType,
        public readonly ?string $senderId,
        public readonly ?string $senderName,
        public readonly ?string $iconUrl,
        public readonly bool $isOpened,
        public readonly bool $isReceived,
        public readonly bool $isProtected,
        public readonly bool $isExpired,
        public readonly bool $isUnread,
        public readonly ?string $expiresAt,
        public readonly ?string $readAt,
        public readonly ?string $receivedAt,
        public readonly string $createdAt,
        public readonly array $contents,
    ) {}
}
