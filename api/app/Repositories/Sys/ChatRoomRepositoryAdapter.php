<?php

namespace App\Repositories\Sys;

use App\Adapters\Chat\ChatRoomAdapter;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusChat\Constants\ChatRoomRole;
use NexusChat\Constants\ChatRoomType;
use NexusChat\Contracts\ChatRoomRepositoryInterface;
use NexusChat\DataTransferObjects\ChatRoom;

/**
 * ChatRoomRepositoryAdapter
 *
 * sys_chat_room の永続化。nexus-chat のインターフェースを直接実装する。
 *
 * チャットはシャードを跨いで会話するため sys に置く。
 * 生成系は採番したIDを載せたDTOを返す必要がある（room_key と
 * ブロードキャストのチャンネル名がIDから決まる）ため、直接INSERTする。
 *
 * パッケージ契約（nexus-chat の Repository interface）の実装。契約単位のため Model と 1 対 1 の規約の対象外（*RepositoryAdapter）。
 * Model を持たず DB::table で直接読み書きする。UnitOfWork のキューを通らないので、書き込みは UseCase のトランザクション終了を待たず即時に実行される。
 */
class ChatRoomRepositoryAdapter implements ChatRoomRepositoryInterface
{
    private const CONNECTION = 'sys';

    private const TABLE = 'sys_chat_room';

    public function selectById(int $id): ?ChatRoom
    {
        $row = DB::connection(self::CONNECTION)->table(self::TABLE)->where('id', $id)->first();

        return $row ? ChatRoomAdapter::toDto((array) $row) : null;
    }

    /**
     * {@inheritDoc}
     *
     * 部屋の行を mutex にする。招待・キック・退室は、先にこの行を取ってから
     * 人数・メンバーを読み直して更新する（architecture.md 7節）。
     * Adapter は UnitOfWork を通らず即時実行なので、ロックはその場で発行される。
     * 接続は sys に固定する（UseCase のトランザクションと同じ接続）。
     */
    public function selectByIdForUpdate(int $id): ?ChatRoom
    {
        $this->assertInTransaction();

        $row = DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('id', $id)
            ->lockForUpdate()
            ->first();

        return $row ? ChatRoomAdapter::toDto((array) $row) : null;
    }

    public function selectByRoomKey(string $roomKey): ?ChatRoom
    {
        $row = DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('room_key', $roomKey)->first();

        return $row ? ChatRoomAdapter::toDto((array) $row) : null;
    }

    /**
     * {@inheritDoc}
     *
     * room_key は「小さいID_大きいID」。どちらから開いても同じ部屋になる。
     * FRIEND は GUILD と違ってメンバー表で参加確認をするため、
     * 部屋を作るときに2人ぶんの行も入れる。
     */
    public function findOrCreateFriendRoom(int $playerIdA, int $playerIdB): ChatRoom
    {
        $roomKey = min($playerIdA, $playerIdB).'_'.max($playerIdA, $playerIdB);

        $existing = $this->selectByRoomKey($roomKey);
        if ($existing !== null) {
            return $existing;
        }

        $now = ClockUtility::nowToString();

        $id = DB::connection(self::CONNECTION)->table(self::TABLE)->insertGetId([
            'type' => ChatRoomType::FRIEND->value,
            'room_key' => $roomKey,
            'name' => null,
            'sys_guild_id' => null,
            'member_count' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([$playerIdA, $playerIdB] as $playerId) {
            DB::connection(self::CONNECTION)->table('sys_chat_room_member')->insert([
                'chat_room_id' => $id,
                'sys_player_id' => $playerId,
                'player_name' => $this->resolvePlayerName($playerId),
                'role' => ChatRoomRole::MEMBER->value,
                'joined_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return new ChatRoom(
            id: $id,
            type: ChatRoomType::FRIEND,
            roomKey: $roomKey,
            name: '',
            guildId: null,
            memberCount: 2,
            createdAt: $now,
        );
    }

    /**
     * {@inheritDoc}
     *
     * ギルドチャットは加入がそのまま参加なので、メンバー表は作らない。
     */
    public function findOrCreateGuildRoom(int $guildId): ChatRoom
    {
        $roomKey = (string) $guildId;

        $existing = $this->selectByRoomKey($roomKey);
        if ($existing !== null) {
            return $existing;
        }

        $now = ClockUtility::nowToString();

        $id = DB::connection(self::CONNECTION)->table(self::TABLE)->insertGetId([
            'type' => ChatRoomType::GUILD->value,
            'room_key' => $roomKey,
            'name' => null,
            'sys_guild_id' => $guildId,
            'member_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new ChatRoom(
            id: $id,
            type: ChatRoomType::GUILD,
            roomKey: $roomKey,
            name: '',
            guildId: $guildId,
            memberCount: 0,
            createdAt: $now,
        );
    }

    /**
     * {@inheritDoc}
     *
     * グループの room_key はID自身。採番前は決まらないため、
     * 一時キーで採番してから自分のIDで上書きする。
     */
    public function createGroupRoom(string $name): ChatRoom
    {
        $now = ClockUtility::nowToString();

        $id = DB::connection(self::CONNECTION)->table(self::TABLE)->insertGetId([
            'type' => ChatRoomType::GROUP->value,
            'room_key' => 'pending_'.uniqid('', true),
            'name' => $name,
            'sys_guild_id' => null,
            'member_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('id', $id)
            ->update(['room_key' => (string) $id, 'updated_at' => $now]);

        return new ChatRoom(
            id: $id,
            type: ChatRoomType::GROUP,
            roomKey: (string) $id,
            name: $name,
            guildId: null,
            memberCount: 0,
            createdAt: $now,
        );
    }

    /**
     * {@inheritDoc}
     *
     * メンバー表を持つ FRIEND / GROUP のみ返す。
     * ギルドチャットは加入情報がギルド側にあるため、ここには出ない。
     */
    public function selectRoomsByPlayerId(int $playerId, array $types = []): array
    {
        $query = DB::connection(self::CONNECTION)
            ->table(self::TABLE.' as r')
            ->join('sys_chat_room_member as m', 'm.chat_room_id', '=', 'r.id')
            ->where('m.sys_player_id', $playerId);

        if ($types !== []) {
            $query->whereIn('r.type', $types);
        }

        return $query->orderByDesc('r.id')
            ->select('r.*')
            ->get()
            ->map(fn ($row) => ChatRoomAdapter::toDto((array) $row))
            ->all();
    }

    public function updateMemberCount(int $chatRoomId, int $memberCount): void
    {
        DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('id', $chatRoomId)
            ->update([
                'member_count' => $memberCount,
                'updated_at' => ClockUtility::nowToString(),
            ]);
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

    private function resolvePlayerName(int $sysPlayerId): string
    {
        $name = DB::connection(self::CONNECTION)->table('sys_player')
            ->where('id', $sysPlayerId)
            ->value('name');

        return $name === null ? '' : (string) $name;
    }
}
