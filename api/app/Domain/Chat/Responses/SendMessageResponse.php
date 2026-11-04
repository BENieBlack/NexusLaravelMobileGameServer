<?php

namespace App\Domain\Chat\Responses;

use App\Domain\_BaseResponse;
use NexusChat\DataTransferObjects\ChatMessage;

/**
 * SendMessageResponse
 *
 * メッセージ送信APIのレスポンス
 */
class SendMessageResponse extends _BaseResponse
{
    public function __construct(
        private readonly ChatMessage $message,
    ) {}

    /**
     * UseCaseの結果からレスポンスを生成
     */
    public static function fromResult(ChatMessage $chatMessage): self
    {
        return new self($chatMessage);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['message' => RoomPresenter::messageToArray($this->message)];
    }
}
