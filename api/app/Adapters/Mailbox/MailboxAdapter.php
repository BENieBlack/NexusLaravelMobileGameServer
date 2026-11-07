<?php

namespace App\Adapters\Mailbox;

use App\Models\Trx\TrxMailbox;
use NexusMailbox\DataTransferObjects\Mailbox;

/**
 * MailboxAdapter
 *
 * TrxMailbox Model と Mailbox の変換を行うアダプター
 */
class MailboxAdapter
{
    /**
     * Model から DTO に変換
     */
    public static function toDto(TrxMailbox $trxMailbox): Mailbox
    {
        return new Mailbox(
            id: $trxMailbox->getId(),
            sysPlayerId: $trxMailbox->getSysPlayerId(),
            mstMailboxId: $trxMailbox->getMstMailboxId(),
            isRead: $trxMailbox->getIsOpened(),
            isReceived: $trxMailbox->getIsReceived(),
            isLocked: $trxMailbox->getIsProtected(),
            expiresAt: $trxMailbox->getExpiresAt(),
            createdAt: (string) $trxMailbox->getCreatedAt(),
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxMailbox>  $trxMailboxs
     * @return array<Mailbox>
     */
    public static function toDtoArray(iterable $trxMailboxs): array
    {
        $dtos = [];
        foreach ($trxMailboxs as $trxMailbox) {
            $dtos[] = self::toDto($trxMailbox);
        }

        return $dtos;
    }
}
