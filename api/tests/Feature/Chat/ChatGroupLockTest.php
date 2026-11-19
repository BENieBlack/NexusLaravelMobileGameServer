<?php

namespace Tests\Feature\Chat;

use App\Exceptions\GameErrorCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * チャットのグループの招待・キック・退室の排他（ルーム行の行ロック）を検証する
 *
 * 同時実行そのものはテストで再現できないので、次の 2 点で守りを固定する。
 *  - 部屋の行が SELECT ... FOR UPDATE で最初にロックされること（SQL の順序を検査）
 *  - ロック後の判定（定員・member_count）が実数どおりに効くこと
 */
class ChatGroupLockTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $ownerPlayerId;

    private string $ownerToken;

    private int $otherPlayerId;

    public function beginDatabaseTransaction(): void
    {
        // UseCaseが自前でトランザクションを張るため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ['player' => $owner, 'token' => $this->ownerToken] = $this->signUpPlayer();
        $this->ownerPlayerId = $owner->id;
        ['player' => $other] = $this->signUpPlayer();
        $this->otherPlayerId = $other->id;
    }

    protected function tearDown(): void
    {
        DB::connection('sys')->table('sys_chat_message')->delete();
        DB::connection('sys')->table('sys_chat_room_member')->delete();
        DB::connection('sys')->table('sys_chat_room')->delete();

        parent::tearDown();
    }

    #[Test]
    public function test_invite_locks_room_row_first(): void
    {
        $roomId = $this->makeGroupRoom();

        $locks = $this->collectLockTables(function () use ($roomId): void {
            $this->invite($roomId, $this->otherPlayerId)->assertOk();
        });

        // 部屋 → 招待者の権限 → 人数 → 招待相手の重複確認、の順
        $this->assertSame(
            ['sys_chat_room', 'sys_chat_room_member', 'sys_chat_room_member', 'sys_chat_room_member'],
            $locks
        );
    }

    #[Test]
    public function test_kick_and_leave_lock_room_row_first(): void
    {
        $roomId = $this->makeGroupRoom();
        $this->invite($roomId, $this->otherPlayerId)->assertOk();

        $kickLocks = $this->collectLockTables(function () use ($roomId): void {
            $this->postAsOwner('/api/chat/group/kick', $roomId, $this->otherPlayerId)->assertOk();
        });
        // 部屋 → 実行者の権限 → 対象の OWNER 確認 → 人数、の順
        $this->assertSame(
            ['sys_chat_room', 'sys_chat_room_member', 'sys_chat_room_member', 'sys_chat_room_member'],
            $kickLocks
        );

        $this->invite($roomId, $this->otherPlayerId)->assertOk();
        $leaveLocks = $this->collectLockTables(function () use ($roomId): void {
            $this->postAsOwner('/api/chat/group/leave', $roomId, null)->assertOk();
        });
        $this->assertSame(['sys_chat_room', 'sys_chat_room_member', 'sys_chat_room_member'], $leaveLocks);
    }

    #[Test]
    public function test_invite_succeeds_one_below_the_limit_and_fails_at_the_limit(): void
    {
        $roomId = $this->makeGroupRoom();
        // オーナー + 18 人 = 19 人にする（上限 20 の 1 つ手前）
        $this->seedMembers($roomId, 18);

        $this->invite($roomId, $this->otherPlayerId)->assertOk();
        $this->assertSame(20, $this->findRoom($roomId)->member_count);
        $this->assertSame(20, $this->countMembers($roomId));

        ['player' => $third] = $this->signUpPlayer();
        $response = $this->invite($roomId, $third->id);

        $this->assertSame(GameErrorCode::CHAT_ROOM_FULL, $response->json('error_code'));
        $this->assertSame(20, $this->countMembers($roomId));
        $this->assertSame(20, $this->findRoom($roomId)->member_count);
    }

    #[Test]
    public function test_member_count_matches_actual_members_after_invite_kick_and_leave(): void
    {
        $roomId = $this->makeGroupRoom();
        // member_count が実数とずれている状態から始めても、書き直しで実数に戻る
        DB::connection('sys')->table('sys_chat_room')->where('id', $roomId)->update(['member_count' => 7]);

        $this->invite($roomId, $this->otherPlayerId)->assertOk();
        $this->assertSame($this->countMembers($roomId), $this->findRoom($roomId)->member_count);
        $this->assertSame(2, $this->findRoom($roomId)->member_count);

        $this->postAsOwner('/api/chat/group/kick', $roomId, $this->otherPlayerId)->assertOk();
        $this->assertSame(1, $this->findRoom($roomId)->member_count);

        $this->invite($roomId, $this->otherPlayerId)->assertOk();
        $this->postAsOwner('/api/chat/group/leave', $roomId, null)->assertOk();
        $this->assertSame($this->countMembers($roomId), $this->findRoom($roomId)->member_count);
        $this->assertSame(1, $this->findRoom($roomId)->member_count);
    }

    /**
     * 処理中に発行された FOR UPDATE 付きの SELECT の、対象テーブルを発行順に返す
     *
     * @return list<string>
     */
    private function collectLockTables(\Closure $callback): array
    {
        $tables = [];
        DB::listen(function ($query) use (&$tables): void {
            if (preg_match('/^\s*select\b.*\bfrom\s+[`"]?(\w+)[`"]?.*\bfor update\s*$/is', $query->sql, $m) === 1) {
                $tables[] = $m[1];
            }
        });

        $callback();

        return $tables;
    }

    private function seedMembers(int $roomId, int $count): void
    {
        $now = date('Y-m-d H:i:s');
        for ($i = 0; $i < $count; $i++) {
            DB::connection('sys')->table('sys_chat_room_member')->insert([
                'chat_room_id' => $roomId,
                'sys_player_id' => 900000 + $i,
                'player_name' => 'dummy'.$i,
                'role' => 'member',
                'joined_at' => $now,
                'updated_at' => $now,
            ]);
        }
        DB::connection('sys')->table('sys_chat_room')->where('id', $roomId)->update(['member_count' => $count + 1]);
    }

    private function countMembers(int $roomId): int
    {
        return DB::connection('sys')->table('sys_chat_room_member')->where('chat_room_id', $roomId)->count();
    }

    private function makeGroupRoom(): int
    {
        return (int) $this->postJson(
            '/api/chat/group/create',
            ['name' => '仲良し'],
            $this->authHeaders($this->ownerToken)
        )->json('room.sys_chat_room_id');
    }

    private function invite(int $roomId, int $targetPlayerId): TestResponse
    {
        return $this->postAsOwner('/api/chat/group/invite', $roomId, $targetPlayerId);
    }

    /**
     * オーナーとして POST する（$targetPlayerId が null なら対象なし。退室用）
     */
    private function postAsOwner(string $uri, int $roomId, ?int $targetPlayerId): TestResponse
    {
        $body = ['sys_chat_room_id' => $roomId];
        if ($targetPlayerId !== null) {
            $body['sys_player_id'] = $targetPlayerId;
        }

        return $this->postJson($uri, $body, $this->authHeaders($this->ownerToken));
    }

    private function findRoom(int $roomId): object
    {
        $row = DB::connection('sys')->table('sys_chat_room')->where('id', $roomId)->first();
        $this->assertNotNull($row);

        return $row;
    }
}
