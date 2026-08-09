<?php

namespace App\Http\Responses\Mailbox;

use App\Http\Responses\_BaseResponse;

/**
 * ReceiveResponse
 *
 * 添付配布物受取レスポンス
 */
class ReceiveResponse extends _BaseResponse
{
    public function __construct(
        private int $trxMailboxId,
        private bool $isReceived,
        private array $receivedContentArray,
    ) {}

    /**
     * レスポンス配列を取得
     */
    public function toArray(): array
    {
        $contentArray = array_map(function ($content) {
            return [
                'type' => $content->getType(),
                'id' => $content->getId(),
                'amount' => $content->getAmount(),
            ];
        }, $this->receivedContentArray);

        return [
            'trx_mailbox_id' => $this->trxMailboxId,
            'is_received' => $this->isReceived,
            'received_content' => $contentArray,
        ];
    }
}
