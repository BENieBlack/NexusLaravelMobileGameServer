<?php

namespace App\Repositories\Sys;

use App\Adapters\Chat\ChatMessageAdapter;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusChat\Contracts\ChatMessageRepositoryInterface;
use NexusChat\DataTransferObjects\ChatMessage;

/**
 * ChatMessageRepositoryAdapter
 *
 * sys_chat_message の永続化。
 *
 * 送信直後のメッセージはブロードキャストに載せるため、
 * 採番したIDを持つDTOをその場で返す必要がある。
 *
 * パッケージ契約（nexus-chat の Repository interface）の実装。契約単位のため Model と 1 対 1 の規約の対象外（*RepositoryAdapter）。
 * Model を持たず DB::table で直接読み書きする。UnitOfWork のキューを通らないので、書き込みは UseCase のトランザクション終了を待たず即時に実行される。
 */
class ChatMessageRepositoryAdapter implements ChatMessageRepositoryInterface
{
    private const CONNECTION = 'sys';

    private const TABLE = 'sys_chat_message';

    /**
     * {@inheritDoc}
     *
     * 新しい順に取り、古い順へ並べ替えて返す。
     * カーソルは「このID未満」で、削除済みは載せない。
     */
    public function selectByRoomId(int $chatRoomId, int $limit = 30, ?int $beforeMessageId = null): array
    {
        $query = DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('chat_room_id', $chatRoomId)
            ->where('is_deleted', false);

        if ($beforeMessageId !== null) {
            $query->where('id', '<', $beforeMessageId);
        }

        return $query->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($row) => ChatMessageAdapter::toDto((array) $row))
            ->all();
    }

    public function selectById(int $messageId): ?ChatMessage
    {
        $row = DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('id', $messageId)
            ->first();

        return $row ? ChatMessageAdapter::toDto((array) $row) : null;
    }

    public function insert(
        int $chatRoomId,
        int $senderPlayerId,
        string $senderName,
        string $body,
    ): ChatMessage {
        $now = ClockUtility::nowToString();

        $id = DB::connection(self::CONNECTION)->table(self::TABLE)->insertGetId([
            'chat_room_id' => $chatRoomId,
            'sender_player_id' => $senderPlayerId,
            'sender_name' => $senderName,
            'body' => $body,
            'is_deleted' => false,
            'created_at' => $now,
        ]);

        return new ChatMessage(
            id: $id,
            chatRoomId: $chatRoomId,
            senderPlayerId: $senderPlayerId,
            senderName: $senderName,
            body: $body,
            isDeleted: false,
            createdAt: $now,
        );
    }

    /**
     * {@inheritDoc}
     *
     * 本文は残したまま論理削除する。通報時の確認に要るため物理削除はしない。
     */
    public function softDelete(int $messageId): void
    {
        DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('id', $messageId)
            ->update(['is_deleted' => true]);
    }
}
