<?php

namespace App\Domain\Mailbox\DataTransferObjects;

/**
 * メール1件に添付された報酬1つ
 */
class MailboxItemContent
{
    public function __construct(
        public readonly string $contentType,
        public readonly string $contentTypeLabel,
        public readonly string $contentTypeIcon,
        public readonly string $contentMstId,
        public readonly int $amount,
        public readonly ?string $rarity,
        public readonly bool $isHighlight,
        public readonly int $sortDesc,
    ) {}
}
