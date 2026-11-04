<?php

namespace App\Domain\Chat\Responses;

use App\Domain\_BaseResponse;
use NexusChat\DataTransferObjects\ChatMessage;

/**
 * MessagesResponse
 *
 * メッセージ履歴APIのレスポンス
 */
class MessagesResponse extends _BaseResponse
{
    /**
     * @param  array<ChatMessage>  $messages
     */
    public function __construct(
        private readonly array $messages,
    ) {}

    /**
     * UseCaseの結果からレスポンスを生成
     *
     * @param  array<ChatMessage>  $chatMessages
     */
    public static function fromResult(array $chatMessages): self
    {
        return new self($chatMessages);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'chat_message_list' => array_map(
                fn (ChatMessage $message): array => RoomPresenter::messageToArray($message),
                $this->messages
            ),
        ];
    }
}
