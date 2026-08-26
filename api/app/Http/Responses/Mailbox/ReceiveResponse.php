<?php

namespace App\Http\Responses\Mailbox;

use App\Http\Responses\_BaseResponse;
use NexusResource\DataTransferObjects\Resource;

/**
 * ReceiveResponse
 *
 * 添付配布物受取レスポンス
 */
class ReceiveResponse extends _BaseResponse
{
    /**
     * @param  array<int, \NexusResource\DataTransferObjects\Resource>  $receivedContentArray  受け取った配布物
     */
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
        $contentArray = array_map(function (Resource $content) {
            return [
                // getType()はEnumを返すので、他のレスポンスと揃えて値で返す
                'type' => $content->getTypeValue(),
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
