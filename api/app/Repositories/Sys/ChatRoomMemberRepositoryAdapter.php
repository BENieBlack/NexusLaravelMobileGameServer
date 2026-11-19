<?php

namespace App\Repositories\Sys;

use App\Adapters\Chat\ChatRoomMemberAdapter;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusChat\Constants\ChatRoomRole;
use NexusChat\Contracts\ChatRoomMemberRepositoryInterface;
use NexusChat\DataTransferObjects\ChatRoomMember;

/**
 * ChatRoomMemberRepositoryAdapter
 *
 * sys_chat_room_member の永続化。
 * GUILD チャットはこの表を使わない（加入がそのまま参加のため）。
 *
 * パッケージ契約（nexus-chat の Repository interface）の実装。契約単位のため Model と 1 対 1 の規約の対象外（*RepositoryAdapter）。
 * Model を持たず DB::table で直接読み書きする。UnitOfWork のキューを通らないので、書き込みは UseCase のトランザクション終了を待たず即時に実行される。
 */
class ChatRoomMemberRepositoryAdapter implements ChatRoomMemberRepositoryInterface
{
    private const CONNECTION = 'sys';

    private const TABLE = 'sys_chat_room_member';

    public function selectByRoomId(int $chatRoomId): array
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('chat_room_id', $chatRoomId)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => ChatRoomMemberAdapter::toDto((array) $row))
            ->all();
    }

    public function selectByRoomAndPlayer(int $chatRoomId, int $playerId): ?ChatRoomMember
    {
        $row = DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('chat_room_id', $chatRoomId)
            ->where('sys_player_id', $playerId)
            ->first();

        return $row ? ChatRoomMemberAdapter::toDto((array) $row) : null;
    }

    /**
     * {@inheritDoc}
     *
     * 該当行が無いときの FOR UPDATE はギャップロックで直列化にならない。
     * 直列化はルーム行のロック（ChatRoomRepositoryAdapter::selectByIdForUpdate）が担い、
     * ここは「ロック後の最新の値」を読む役（current read）。
     */
    public function selectByRoomAndPlayerForUpdate(int $chatRoomId, int $playerId): ?ChatRoomMember
    {
        $this->assertInTransaction();

        $row = DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('chat_room_id', $chatRoomId)
            ->where('sys_player_id', $playerId)
            ->lockForUpdate()
            ->first();

        return $row ? ChatRoomMemberAdapter::toDto((array) $row) : null;
    }

    public function insert(
        int $chatRoomId,
        int $playerId,
        string $playerName,
        ChatRoomRole $role,
    ): ChatRoomMember {
        $now = ClockUtility::nowToString();

        $id = DB::connection(self::CONNECTION)->table(self::TABLE)->insertGetId([
            'chat_room_id' => $chatRoomId,
            'sys_player_id' => $playerId,
            'player_name' => $playerName,
            'role' => $role->value,
            'joined_at' => $now,
            'updated_at' => $now,
        ]);

        return new ChatRoomMember(
            id: $id,
            chatRoomId: $chatRoomId,
            playerId: $playerId,
            playerName: $playerName,
            role: $role,
            joinedAt: $now,
        );
    }

    public function updateRole(int $chatRoomId, int $playerId, ChatRoomRole $newRole): void
    {
        DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('chat_room_id', $chatRoomId)
            ->where('sys_player_id', $playerId)
            ->update([
                'role' => $newRole->value,
                'updated_at' => ClockUtility::nowToString(),
            ]);
    }

    public function delete(int $chatRoomId, int $playerId): void
    {
        DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('chat_room_id', $chatRoomId)
            ->where('sys_player_id', $playerId)
            ->delete();
    }

    public function countByRoomId(int $chatRoomId): int
    {
        return DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('chat_room_id', $chatRoomId)
            ->count();
    }

    /**
     * {@inheritDoc}
     *
     * COUNT(*) に FOR UPDATE は付けても current read にならない場合があるため、
     * 行を読んで数える（SysGuildMemberRepository::countByGuildIdForUpdate と同じ）。
     */
    public function countByRoomIdForUpdate(int $chatRoomId): int
    {
        $this->assertInTransaction();

        return count(DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('chat_room_id', $chatRoomId)
            ->lockForUpdate()
            ->pluck('id')
            ->all());
    }

    /**
     * トランザクションの外の行ロックは即座に外れて何も守れないので、例外にして気づけるようにする
     *
     * @throws \LogicException トランザクション内でない場合
     */
    private function assertInTransaction(): void
    {
        if (DB::connection(self::CONNECTION)->transactionLevel() === 0) {
            throw new \LogicException(sprintf(
                '%s: 行ロックはトランザクション内でしか効かない。executeWithTransaction() の中で呼ぶこと',
                static::class
            ));
        }
    }
}
