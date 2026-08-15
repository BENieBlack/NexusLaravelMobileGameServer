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
    public static function toDto(TrxMailbox $model): Mailbox
    {
        return new Mailbox(
            id: $model->getId(),
            sysPlayerId: $model->getSysPlayerId(),
            mstMailboxId: $model->getMstMailboxId(),
            isRead: $model->getIsOpened(),
            isReceived: $model->getIsReceived(),
            isLocked: $model->getIsProtected(),
            expiresAt: $model->getExpiresAt(),
            createdAt: (string) $model->created_at,
        );
    }

    /**
     * Model配列 から DTO配列 に変換
     *
     * @param  iterable<TrxMailbox>  $models
     * @return array<Mailbox>
     */
    public static function toDtoArray(iterable $models): array
    {
        $dtos = [];
        foreach ($models as $model) {
            $dtos[] = self::toDto($model);
        }

        return $dtos;
    }
}
