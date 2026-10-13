<?php

namespace App\Domain\Mailbox\DataTransferObjects;

/**
 * ReceiveResult
 *
 * 添付配布物受取の結果
 */
class ReceiveResult
{
    /**
     * @param  array<int, \NexusResource\DataTransferObjects\Resource>  $receivedContentArray  受け取った配布物
     */
    public function __construct(
        public readonly int $trxMailboxId,
        public readonly bool $isReceived,
        public readonly array $receivedContentArray,
    ) {}
}
