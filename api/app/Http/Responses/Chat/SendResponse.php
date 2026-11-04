<?php

namespace App\Http\Responses\Chat;

use App\Http\Responses\_BaseResponse;
use NexusChat\DataTransferObjects\ChatMessage;

/**
 * SendResponse
 *
 * メッセージ送信APIのレスポンス
 */
class SendResponse extends _BaseResponse
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
