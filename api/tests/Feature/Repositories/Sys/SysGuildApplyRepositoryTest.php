<?php

namespace Tests\Feature\Repositories\Sys;

use App\Models\Sys\SysGuildApply;
use App\Persistence\ApiSession;
use App\Repositories\Sys\SysGuildApplyRepository;
use Illuminate\Support\Facades\DB;
use NexusGuild\Constants\GuildApplyStatus;
use NexusGuild\Constants\GuildRole;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * SysGuildApplyRepository のテスト
 *
 * 自分の行は「自分が出した申請」と「自分が所属するギルド宛の申請」の両方。
 * マスターが承認するときは他人の申請を書き換えるが、
 * 自分のギルド宛である限りキャッシュ経由で扱える。
 *
 * 所属していないギルド宛の申請はキャッシュに載せない。
 */
class SysGuildApplyRepositoryTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $me = 9101;

    private int $applicant = 9102;

    private int $stranger = 9103;

    private int $myGuildId = 8101;

    private int $otherGuildId = 8102;

    private SysGuildApplyRepository $repository;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的にフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ApiSession::clearForTest();
        ApiSession::setSysPlayerId($this->me);

        $this->repository = app(SysGuildApplyRepository::class);
        $this->queryManager = app(QueryManager::class);

        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        ApiSession::clearForTest();
        $this->queryManager->clear();

        parent::tearDown();
    }

    // ========================================
    // 自分が出した申請（未所属のとき）
    // ========================================

    #[Test]
    public function 自分が出した申請を引ける(): void
    {
        $id = $this->makeApply($this->otherGuildId, $this->me);

        $this->assertSame($id, $this->repository->selectByGuildAndPlayer($this->otherGuildId, $this->me)?->getId());
    }

    #[Test]
    public function 決着済みの申請は引けない(): void
    {
        // 却下されたギルドへは再申請できる
        $this->makeApply($this->otherGuildId, $this->me, GuildApplyStatus::REJECTED);

        $this->assertNull($this->repository->selectByGuildAndPlayer($this->otherGuildId, $this->me));
    }

    #[Test]
    public function 承認済みの申請は引ける(): void
    {
        // 既に所属しているので重ねて申請できない
        $this->makeApply($this->otherGuildId, $this->me, GuildApplyStatus::ACCEPTED);

        $this->assertNotNull($this->repository->selectByGuildAndPlayer($this->otherGuildId, $this->me));
    }

    #[Test]
    public function 自分の申請一覧を引ける(): void
    {
        $mine = $this->makeApply($this->otherGuildId, $this->me);
        $this->makeApply($this->otherGuildId, $this->applicant);

        $applies = $this->repository->selectAppliesByPlayerId($this->me);

        $this->assertCount(1, $applies);
        $this->assertSame($mine, $applies->first()->getId());
    }

    #[Test]
    public function 決着済みは申請一覧に入らない(): void
    {
        $this->makeApply($this->otherGuildId, $this->me, GuildApplyStatus::ACCEPTED);

        $this->assertCount(0, $this->repository->selectAppliesByPlayerId($this->me));
    }

    // ========================================
    // 所属ギルド宛の申請（マスターとして捌く）
    // ========================================

    #[Test]
    public function 所属ギルド宛の申請一覧を引ける(): void
    {
        $this->joinGuild($this->me, $this->myGuildId, GuildRole::MASTER);
        $target = $this->makeApply($this->myGuildId, $this->applicant);
        $this->makeApply($this->otherGuildId, $this->stranger);

        $applies = $this->repository->selectAppliesByGuildId($this->myGuildId);

        $this->assertCount(1, $applies);
        $this->assertSame($target, $applies->first()->getId());
    }

    #[Test]
    public function 他人が出した申請でも自分のギルド宛ならキャッシュに載る(): void
    {
        // 承認・却下でこの行を書き換えるため、更新できる必要がある
        $this->joinGuild($this->me, $this->myGuildId, GuildRole::MASTER);
        $target = $this->makeApply($this->myGuildId, $this->applicant);

        $this->assertContains(
            $target,
            $this->repository->queryOrMemory()->map(fn (SysGuildApply $apply) => $apply->getId())->values()->all()
        );
    }

    #[Test]
    public function 所属していないギルド宛の申請はキャッシュに載らない(): void
    {
        $this->joinGuild($this->me, $this->myGuildId, GuildRole::MASTER);
        $others = $this->makeApply($this->otherGuildId, $this->applicant);

        $this->assertNotContains(
            $others,
            $this->repository->queryOrMemory()->map(fn (SysGuildApply $apply) => $apply->getId())->values()->all()
        );

        // 読むことはできる
        $this->assertCount(1, $this->repository->selectAppliesByGuildId($this->otherGuildId));
    }

    #[Test]
    public function 未所属なら他ギルド宛の一覧もキャッシュを通さない(): void
    {
        $this->makeApply($this->myGuildId, $this->applicant);

        $this->assertCount(1, $this->repository->selectAppliesByGuildId($this->myGuildId));
        $this->assertCount(0, $this->repository->queryOrMemory(), '自分の申請が無ければ空');
    }

    #[Test]
    public function 他人の申請をギルドとプレイヤーで引ける(): void
    {
        $id = $this->makeApply($this->myGuildId, $this->applicant);

        $this->assertSame($id, $this->repository->selectByGuildAndPlayer($this->myGuildId, $this->applicant)?->getId());
    }

    #[Test]
    public function 申請一覧に決着済みは入らない(): void
    {
        $this->joinGuild($this->me, $this->myGuildId, GuildRole::MASTER);
        $this->makeApply($this->myGuildId, $this->applicant, GuildApplyStatus::ACCEPTED);
        $this->makeApply($this->myGuildId, $this->stranger, GuildApplyStatus::REJECTED);

        $this->assertCount(0, $this->repository->selectAppliesByGuildId($this->myGuildId));
    }

    // ========================================
    // 作成
    // ========================================

    #[Test]
    public function 申請を作れる(): void
    {
        $apply = $this->repository->insertApply($this->otherGuildId, $this->me);
        $this->queryManager->execAllQuery();

        $this->assertSame(GuildApplyStatus::APPLIED, $apply->getStatus());
        $this->assertDatabaseHas('sys_guild_apply', [
            'sys_guild_id' => $this->otherGuildId,
            'sys_player_id' => $this->me,
            'status' => GuildApplyStatus::APPLIED,
        ], 'sys');
    }

    #[Test]
    public function 作った申請は同じリクエスト内で読み戻せる(): void
    {
        $this->repository->insertApply($this->otherGuildId, $this->me);

        $this->assertNotNull($this->repository->selectByGuildAndPlayer($this->otherGuildId, $this->me));
    }

    private function makeApply(int $guildId, int $playerId, string $status = GuildApplyStatus::APPLIED): int
    {
        return DB::connection('sys')->table('sys_guild_apply')->insertGetId([
            'sys_guild_id' => $guildId,
            'sys_player_id' => $playerId,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function joinGuild(int $playerId, int $guildId, string $role): void
    {
        DB::connection('sys')->table('sys_guild_member')->insert([
            'sys_guild_id' => $guildId,
            'sys_player_id' => $playerId,
            'role' => $role,
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cleanUp(): void
    {
        $players = [$this->me, $this->applicant, $this->stranger];
        DB::connection('sys')->table('sys_guild_apply')->whereIn('sys_player_id', $players)->delete();
        DB::connection('sys')->table('sys_guild_member')->whereIn('sys_player_id', $players)->delete();
        $this->queryManager->clear();
    }
}
