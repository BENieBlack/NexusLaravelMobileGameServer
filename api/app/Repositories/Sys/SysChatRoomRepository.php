<?php

namespace App\Repositories\Sys;

use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusChat\Constants\ChatRoomRole;
use NexusChat\Constants\ChatRoomType;
use NexusChat\Contracts\ChatRoomRepositoryInterface;
use NexusChat\DataTransferObjects\ChatRoom;

/**
 * SysChatRoomRepository
 *
 * sys_chat_room の永続化。nexus-chat のインターフェースを直接実装する。
 *
 * チャットはシャードを跨いで会話するため sys に置く。
 * 生成系は採番したIDを載せたDTOを返す必要がある（room_key と
 * ブロードキャストのチャンネル名がIDから決まる）ため、直接INSERTする。
 */
class SysChatRoomRepository implements ChatRoomRepositoryInterface
{
    private const CONNECTION = 'sys';

    private const TABLE = 'sys_chat_room';

    public function selectById(int $id): ?ChatRoom
    {
        $row = DB::connection(self::CONNECTION)->table(self::TABLE)->where('id', $id)->first();

        return $row ? $this->toDto((array) $row) : null;
    }

    public function selectByRoomKey(string $roomKey): ?ChatRoom
    {
        $row = DB::connection(self::CONNECTION)->table(self::TABLE)
            ->where('room_key', $roomKey)->first();

        return $row ? $this->toDto((array) $row) : null;
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
            ->map(fn ($row) => $this->toDto((array) $row))
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

    private function resolvePlayerName(int $sysPlayerId): string
    {
        $name = DB::connection(self::CONNECTION)->table('sys_player')
            ->where('id', $sysPlayerId)
            ->value('name');

        return $name === null ? '' : (string) $name;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function toDto(array $row): ChatRoom
    {
        return new ChatRoom(
            id: (int) $row['id'],
            type: ChatRoomType::from((string) $row['type']),
            roomKey: (string) $row['room_key'],
            name: $row['name'] === null ? '' : (string) $row['name'],
            guildId: $row['sys_guild_id'] === null ? null : (int) $row['sys_guild_id'],
            memberCount: (int) $row['member_count'],
            createdAt: (string) $row['created_at'],
        );
    }
}
