<?php

namespace NexusGuild\Tests\Unit\Services;

use NexusGuild\Constants\GuildApplyStatus;
use NexusGuild\Constants\GuildRole;
use NexusGuild\DataTransferObjects\Guild;
use NexusGuild\DataTransferObjects\GuildApply;
use NexusGuild\DataTransferObjects\GuildMember;
use NexusGuild\Exceptions\GuildException;
use NexusGuild\Repositories\GuildApplyRepositoryInterface;
use NexusGuild\Repositories\GuildMemberRepositoryInterface;
use NexusGuild\Repositories\GuildRepositoryInterface;
use NexusGuild\Services\GuildService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * GuildService のユニットテスト
 *
 * 永続化は3つのRepositoryInterfaceの向こう側なので、
 * メモリ上の実装を差し込んで検証する。
 *
 * 「誰が」「どの状態のものに」対して操作できるかが要点。
 * 承認・却下は役職で守られており、マスターは脱退できない。
 */
class GuildServiceTest extends TestCase
{
    private const GUILD_ID = 10;

    private const MASTER_ID = 1;

    private const MEMBER_ID = 2;

    private const APPLICANT_ID = 3;

    private const APPLY_ID = 100;

    private FakeGuildRepository $guildRepository;

    private FakeGuildApplyRepository $applyRepository;

    private FakeGuildMemberRepository $memberRepository;

    private GuildService $service;

    protected function setUp(): void
    {
        $this->guildRepository = new FakeGuildRepository;
        $this->applyRepository = new FakeGuildApplyRepository;
        $this->memberRepository = new FakeGuildMemberRepository;

        $this->service = new GuildService(
            $this->guildRepository,
            $this->applyRepository,
            $this->memberRepository,
        );
    }

    // ========================================
    // ギルド作成
    // ========================================

    #[Test]
    public function ギルドを作成できる(): void
    {
        $guild = $this->service->createGuild('テストギルド', '説明', self::MASTER_ID);

        $this->assertSame('テストギルド', $guild->getName());
        $this->assertSame([['テストギルド', '説明', self::MASTER_ID]], $this->guildRepository->inserted);
    }

    #[Test]
    public function 同じ名前のギルドは作れない(): void
    {
        $this->guildRepository->byName = $this->guild();

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('already exists');

        $this->service->createGuild('テストギルド', '説明', self::MASTER_ID);
    }

    #[Test]
    public function 既にギルドに所属していると作れない(): void
    {
        $this->memberRepository->byPlayerId = $this->member(self::MASTER_ID);

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('already in a guild');

        $this->service->createGuild('テストギルド', '説明', self::MASTER_ID);
    }

    // ========================================
    // 加入申請
    // ========================================

    #[Test]
    public function 加入申請を送れる(): void
    {
        $this->guildRepository->byId = $this->guild();

        $apply = $this->service->sendApply(self::GUILD_ID, self::APPLICANT_ID);

        $this->assertSame(GuildApplyStatus::APPLIED, $apply->getStatus());
        $this->assertSame([[self::GUILD_ID, self::APPLICANT_ID]], $this->applyRepository->inserted);
    }

    #[Test]
    public function 存在しないギルドには申請できない(): void
    {
        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('not found');

        $this->service->sendApply(self::GUILD_ID, self::APPLICANT_ID);
    }

    #[Test]
    public function 既にどこかに所属していると申請できない(): void
    {
        $this->guildRepository->byId = $this->guild();
        $this->memberRepository->byPlayerId = $this->member(self::APPLICANT_ID);

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('already in a guild');

        $this->service->sendApply(self::GUILD_ID, self::APPLICANT_ID);
    }

    #[Test]
    public function 同じギルドへ重ねて申請できない(): void
    {
        $this->guildRepository->byId = $this->guild();
        $this->applyRepository->byGuildAndPlayer = $this->apply();

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('Apply already exists');

        $this->service->sendApply(self::GUILD_ID, self::APPLICANT_ID);
    }

    #[Test]
    public function 満員のギルドには申請できない(): void
    {
        $this->guildRepository->byId = $this->guild(currentMembers: 30, maxMembers: 30);

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('full');

        $this->service->sendApply(self::GUILD_ID, self::APPLICANT_ID);
    }

    // ========================================
    // 申請の承認
    // ========================================

    #[Test]
    public function マスターは申請を承認できる(): void
    {
        $this->givenPendingApply(GuildRole::MASTER);

        $accepted = $this->service->acceptApply(self::APPLY_ID, self::MASTER_ID);

        $this->assertSame(GuildApplyStatus::ACCEPTED, $accepted->getStatus());
    }

    #[Test]
    public function サブマスターも申請を承認できる(): void
    {
        $this->givenPendingApply(GuildRole::SUB_MASTER);

        $accepted = $this->service->acceptApply(self::APPLY_ID, self::MEMBER_ID);

        $this->assertSame(GuildApplyStatus::ACCEPTED, $accepted->getStatus());
    }

    #[Test]
    public function 一般メンバーは申請を承認できない(): void
    {
        $this->givenPendingApply(GuildRole::MEMBER);

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('does not have permission to accept guild apply');

        $this->service->acceptApply(self::APPLY_ID, self::MEMBER_ID);
    }

    #[Test]
    public function ギルド外の人は申請を承認できない(): void
    {
        $this->applyRepository->byId = $this->apply();
        $this->guildRepository->byId = $this->guild();
        // selectByGuildAndPlayer が null＝そのギルドのメンバーではない

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('Member not found');

        $this->service->acceptApply(self::APPLY_ID, self::MASTER_ID);
    }

    #[Test]
    public function 存在しない申請は承認できない(): void
    {
        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('Apply not found');

        $this->service->acceptApply(self::APPLY_ID, self::MASTER_ID);
    }

    #[Test]
    public function 決着済みの申請は承認できない(): void
    {
        foreach ([GuildApplyStatus::ACCEPTED, GuildApplyStatus::REJECTED] as $status) {
            $this->givenPendingApply(GuildRole::MASTER);
            $this->applyRepository->byId = $this->apply(status: $status);

            try {
                $this->service->acceptApply(self::APPLY_ID, self::MASTER_ID);
                $this->fail("{$status} なのに承認できてしまった");
            } catch (GuildException $e) {
                $this->assertStringContainsString('Invalid status', $e->getMessage());
            }
        }
    }

    #[Test]
    public function 承認直前に満員になっていたら承認できない(): void
    {
        // 申請時のチェックだけでは、複数人を同時に承認したときに定員を超える
        $this->givenPendingApply(GuildRole::MASTER);
        $this->guildRepository->byId = $this->guild(currentMembers: 30, maxMembers: 30);

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('full');

        $this->service->acceptApply(self::APPLY_ID, self::MASTER_ID);
    }

    #[Test]
    public function 申請先のギルドが消えていたら承認できない(): void
    {
        $this->applyRepository->byId = $this->apply();
        $this->memberRepository->byGuildAndPlayer = $this->member(self::MASTER_ID, GuildRole::MASTER);

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('Guild not found');

        $this->service->acceptApply(self::APPLY_ID, self::MASTER_ID);
    }

    // ========================================
    // 申請の却下
    // ========================================

    #[Test]
    public function マスターは申請を却下できる(): void
    {
        $this->givenPendingApply(GuildRole::MASTER);

        $rejected = $this->service->rejectApply(self::APPLY_ID, self::MASTER_ID);

        $this->assertSame(GuildApplyStatus::REJECTED, $rejected->getStatus());
    }

    #[Test]
    public function 一般メンバーは申請を却下できない(): void
    {
        $this->givenPendingApply(GuildRole::MEMBER);

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('does not have permission to reject guild apply');

        $this->service->rejectApply(self::APPLY_ID, self::MEMBER_ID);
    }

    #[Test]
    public function ギルド外の人は申請を却下できない(): void
    {
        $this->applyRepository->byId = $this->apply();

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('Member not found');

        $this->service->rejectApply(self::APPLY_ID, self::MASTER_ID);
    }

    #[Test]
    public function 存在しない申請は却下できない(): void
    {
        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('Apply not found');

        $this->service->rejectApply(self::APPLY_ID, self::MASTER_ID);
    }

    #[Test]
    public function 決着済みの申請は却下できない(): void
    {
        $this->givenPendingApply(GuildRole::MASTER);
        $this->applyRepository->byId = $this->apply(status: GuildApplyStatus::REJECTED);

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('Invalid status');

        $this->service->rejectApply(self::APPLY_ID, self::MASTER_ID);
    }

    #[Test]
    public function 却下は満員でも通る(): void
    {
        // 却下はメンバーを増やさないので定員の確認は要らない
        $this->givenPendingApply(GuildRole::MASTER);
        $this->guildRepository->byId = $this->guild(currentMembers: 30, maxMembers: 30);

        $rejected = $this->service->rejectApply(self::APPLY_ID, self::MASTER_ID);

        $this->assertSame(GuildApplyStatus::REJECTED, $rejected->getStatus());
    }

    // ========================================
    // 脱退
    // ========================================

    #[Test]
    public function 一般メンバーは脱退できる(): void
    {
        $member = $this->member(self::MEMBER_ID, GuildRole::MEMBER);
        $this->memberRepository->byPlayerId = $member;

        $this->service->leaveGuild(self::MEMBER_ID);

        $this->assertSame([$member], $this->memberRepository->deleted);
        $this->assertSame([self::MEMBER_ID], $this->applyRepository->deletedPlayerIds, '申請も残さない');
    }

    #[Test]
    public function サブマスターも脱退できる(): void
    {
        $this->memberRepository->byPlayerId = $this->member(self::MEMBER_ID, GuildRole::SUB_MASTER);

        $this->service->leaveGuild(self::MEMBER_ID);

        $this->assertCount(1, $this->memberRepository->deleted);
    }

    #[Test]
    public function マスターは脱退できない(): void
    {
        // マスター不在のギルドが残らないようにする
        $this->memberRepository->byPlayerId = $this->member(self::MASTER_ID, GuildRole::MASTER);

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('Master cannot leave');

        $this->service->leaveGuild(self::MASTER_ID);
    }

    #[Test]
    public function ギルドに居なければ脱退できない(): void
    {
        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('Player not in any guild');

        $this->service->leaveGuild(self::MEMBER_ID);
    }

    #[Test]
    public function 脱退に失敗したらメンバーも申請も消えない(): void
    {
        $this->memberRepository->byPlayerId = $this->member(self::MASTER_ID, GuildRole::MASTER);

        try {
            $this->service->leaveGuild(self::MASTER_ID);
        } catch (GuildException) {
            // 権限チェックで弾かれる想定
        }

        $this->assertSame([], $this->memberRepository->deleted);
        $this->assertSame([], $this->applyRepository->deletedPlayerIds);
    }

    // ========================================
    // 参照系
    // ========================================

    #[Test]
    public function 申請一覧とメンバー一覧を取得できる(): void
    {
        $this->applyRepository->appliesByGuildId = [$this->apply()];
        $this->memberRepository->byGuildId = [$this->member(self::MEMBER_ID)];

        $this->assertCount(1, $this->service->findApplyList(self::GUILD_ID));
        $this->assertCount(1, $this->service->findMemberList(self::GUILD_ID));
    }

    #[Test]
    public function ギルド一覧を件数を区切って取得できる(): void
    {
        $this->guildRepository->all = [$this->guild()];

        $this->assertCount(1, $this->service->findGuildList(50, 0));
    }

    #[Test]
    public function 所属ギルドを取得できる(): void
    {
        $this->memberRepository->byPlayerId = $this->member(self::MEMBER_ID);
        $this->guildRepository->byId = $this->guild();

        $this->assertSame(self::GUILD_ID, $this->service->findPlayerGuild(self::MEMBER_ID)?->getId());
    }

    #[Test]
    public function どこにも所属していなければ所属ギルドはnull(): void
    {
        $this->assertNull($this->service->findPlayerGuild(self::MEMBER_ID));
    }

    // ========================================
    // 単体のバリデーション
    // ========================================

    #[Test]
    public function 所属していることのバリデーションはメンバーを返す(): void
    {
        $member = $this->member(self::MEMBER_ID);
        $this->memberRepository->byPlayerId = $member;

        $this->assertSame($member, $this->service->validatePlayerInGuild(self::MEMBER_ID));
    }

    #[Test]
    public function マスター限定のバリデーションはサブマスターも弾く(): void
    {
        $this->service->validateMasterPermission($this->member(self::MASTER_ID, GuildRole::MASTER), 'disband');

        $this->expectException(GuildException::class);
        $this->expectExceptionMessage('does not have permission to disband');

        $this->service->validateMasterPermission($this->member(self::MEMBER_ID, GuildRole::SUB_MASTER), 'disband');
    }

    #[Test]
    public function 定員に達していなければ満員ではない(): void
    {
        $this->service->validateGuildNotFull($this->guild(currentMembers: 29, maxMembers: 30));

        $this->expectException(GuildException::class);
        $this->service->validateGuildNotFull($this->guild(currentMembers: 31, maxMembers: 30));
    }

    /**
     * 承認・却下できる状態を作る（申請あり・承認者は指定の役職）
     */
    private function givenPendingApply(string $approverRole): void
    {
        $this->applyRepository->byId = $this->apply();
        $this->guildRepository->byId = $this->guild();
        $this->memberRepository->byGuildAndPlayer = $this->member(
            $approverRole === GuildRole::MASTER ? self::MASTER_ID : self::MEMBER_ID,
            $approverRole
        );
    }

    private function guild(int $currentMembers = 1, int $maxMembers = 30): Guild
    {
        return new Guild(
            id: self::GUILD_ID,
            name: 'テストギルド',
            description: '説明',
            level: 1,
            exp: 0,
            maxMembers: $maxMembers,
            currentMembers: $currentMembers,
            createdAt: '2026-03-15 12:00:00',
            updatedAt: '2026-03-15 12:00:00',
        );
    }

    private function apply(string $status = GuildApplyStatus::APPLIED): GuildApply
    {
        return new GuildApply(
            id: self::APPLY_ID,
            sysGuildId: self::GUILD_ID,
            sysPlayerId: self::APPLICANT_ID,
            status: $status,
            createdAt: '2026-03-15 12:00:00',
            updatedAt: '2026-03-15 12:00:00',
        );
    }

    private function member(int $playerId, string $role = GuildRole::MEMBER): GuildMember
    {
        return new GuildMember(
            id: $playerId,
            sysGuildId: self::GUILD_ID,
            sysPlayerId: $playerId,
            role: $role,
            joinedAt: '2026-03-15 12:00:00',
            createdAt: '2026-03-15 12:00:00',
            updatedAt: '2026-03-15 12:00:00',
        );
    }
}

/**
 * メモリ上で完結するGuildRepositoryInterface実装
 */
class FakeGuildRepository implements GuildRepositoryInterface
{
    public ?Guild $byId = null;

    public ?Guild $byName = null;

    /** @var array<Guild> */
    public array $all = [];

    /** @var list<array{0: string, 1: string, 2: int}> */
    public array $inserted = [];

    public function selectById(int $guildId): ?Guild
    {
        return $this->byId;
    }

    public function selectByName(string $name): ?Guild
    {
        return $this->byName;
    }

    public function selectList(int $limit, int $offset): array
    {
        return array_slice($this->all, $offset, $limit);
    }

    public function insert(string $name, string $description, int $masterPlayerId): Guild
    {
        $this->inserted[] = [$name, $description, $masterPlayerId];

        return new Guild(
            id: 10,
            name: $name,
            description: $description,
            level: 1,
            exp: 0,
            maxMembers: 30,
            currentMembers: 1,
            createdAt: '2026-03-15 12:00:00',
            updatedAt: '2026-03-15 12:00:00',
        );
    }

    public function update(Guild $guild, array $data): Guild
    {
        return $guild;
    }

    public function delete(Guild $guild): void {}

    public function addExp(Guild $guild, int $exp): Guild
    {
        return $guild;
    }

    public function updateLevel(Guild $guild, int $level, int $exp): Guild
    {
        return $guild;
    }
}

/**
 * メモリ上で完結するGuildApplyRepositoryInterface実装
 */
class FakeGuildApplyRepository implements GuildApplyRepositoryInterface
{
    public ?GuildApply $byId = null;

    public ?GuildApply $byGuildAndPlayer = null;

    /** @var array<GuildApply> */
    public array $appliesByGuildId = [];

    /** @var array<GuildApply> */
    public array $appliesByPlayerId = [];

    /** @var list<array{0: int, 1: int}> */
    public array $inserted = [];

    /** @var list<int> */
    public array $deletedPlayerIds = [];

    public function selectById(int $applyId): ?GuildApply
    {
        return $this->byId;
    }

    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?GuildApply
    {
        return $this->byGuildAndPlayer;
    }

    public function selectAppliesByGuildId(int $guildId): array
    {
        return $this->appliesByGuildId;
    }

    public function selectAppliesByPlayerId(int $playerId): array
    {
        return $this->appliesByPlayerId;
    }

    public function insert(int $guildId, int $playerId): GuildApply
    {
        $this->inserted[] = [$guildId, $playerId];

        return new GuildApply(
            id: 100,
            sysGuildId: $guildId,
            sysPlayerId: $playerId,
            status: GuildApplyStatus::APPLIED,
            createdAt: '2026-03-15 12:00:00',
            updatedAt: '2026-03-15 12:00:00',
        );
    }

    public function accept(GuildApply $guildApply): GuildApply
    {
        return $this->withStatus($guildApply, GuildApplyStatus::ACCEPTED);
    }

    public function reject(GuildApply $guildApply): GuildApply
    {
        return $this->withStatus($guildApply, GuildApplyStatus::REJECTED);
    }

    public function deleteByPlayerId(int $playerId): void
    {
        $this->deletedPlayerIds[] = $playerId;
    }

    /**
     * DTOはreadonlyなので、状態だけ差し替えた新しいインスタンスを返す
     */
    private function withStatus(GuildApply $guildApply, string $status): GuildApply
    {
        return new GuildApply(
            id: $guildApply->getId(),
            sysGuildId: $guildApply->getSysGuildId(),
            sysPlayerId: $guildApply->getSysPlayerId(),
            status: $status,
            createdAt: $guildApply->getCreatedAt(),
            updatedAt: '2026-03-15 12:30:00',
        );
    }
}

/**
 * メモリ上で完結するGuildMemberRepositoryInterface実装
 */
class FakeGuildMemberRepository implements GuildMemberRepositoryInterface
{
    public ?GuildMember $byId = null;

    public ?GuildMember $byGuildAndPlayer = null;

    public ?GuildMember $byPlayerId = null;

    /** @var array<GuildMember> */
    public array $byGuildId = [];

    public int $count = 0;

    /** @var list<GuildMember> */
    public array $deleted = [];

    /** @var list<int> */
    public array $deletedPlayerIds = [];

    public function selectById(int $memberId): ?GuildMember
    {
        return $this->byId;
    }

    public function selectByGuildAndPlayer(int $guildId, int $playerId): ?GuildMember
    {
        return $this->byGuildAndPlayer;
    }

    public function selectByPlayerId(int $playerId): ?GuildMember
    {
        return $this->byPlayerId;
    }

    public function selectByGuildId(int $guildId): array
    {
        return $this->byGuildId;
    }

    public function countByGuildId(int $guildId): int
    {
        return $this->count;
    }

    public function insert(int $guildId, int $playerId, string $role): GuildMember
    {
        return new GuildMember(
            id: 1,
            sysGuildId: $guildId,
            sysPlayerId: $playerId,
            role: $role,
            joinedAt: '2026-03-15 12:00:00',
            createdAt: '2026-03-15 12:00:00',
            updatedAt: '2026-03-15 12:00:00',
        );
    }

    public function updateRole(GuildMember $guildMember, string $role): GuildMember
    {
        return $guildMember;
    }

    public function delete(GuildMember $guildMember): void
    {
        $this->deleted[] = $guildMember;
    }

    public function deleteByPlayerId(int $playerId): void
    {
        $this->deletedPlayerIds[] = $playerId;
    }
}
