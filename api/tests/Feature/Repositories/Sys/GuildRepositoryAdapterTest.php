<?php

namespace Tests\Feature\Repositories\Sys;

use App\Persistence\ApiSession;
use Illuminate\Support\Facades\DB;
use NexusGuild\Constants\GuildRole;
use NexusGuild\DataTransferObjects\Guild;
use NexusGuild\Repositories\GuildRepositoryInterface;
use NexusUnitOfWork\Persistence\QueryManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\RefreshMultipleDatabases;
use Tests\TestCase;

/**
 * GuildRepositoryAdapter のテスト
 *
 * パッケージ層のGuildRepositoryInterfaceの実装。
 * Model ↔ DTO の詰め替えと、変更がDBに届くことを確認する。
 *
 * currentMembers はギルドに属する行を数えた値でカラムではないため、
 * メンバーの増減がそのままDTOに出るところが要点。
 */
class GuildRepositoryAdapterTest extends TestCase
{
    use RefreshMultipleDatabases;

    private int $masterPlayerId;

    private GuildRepositoryInterface $repository;

    private QueryManager $queryManager;

    public function beginDatabaseTransaction(): void
    {
        // QueryManagerで明示的にフラッシュするため自動ラップしない
    }

    protected function setUp(): void
    {
        parent::setUp();

        ['player' => $player] = $this->signUpPlayer();
        $this->masterPlayerId = $player->id;
        ApiSession::setSysPlayerId($this->masterPlayerId);

        $this->repository = app(GuildRepositoryInterface::class);
        $this->queryManager = app(QueryManager::class);
    }

    protected function tearDown(): void
    {
        DB::connection('sys')->table('sys_guild_member')->delete();
        DB::connection('sys')->table('sys_guild_apply')->delete();
        DB::connection('sys')->table('sys_guild')->delete();
        ApiSession::clearForTest();
        $this->queryManager->clear();

        parent::tearDown();
    }

    #[Test]
    public function ギルドを作るとマスターも一緒に登録される(): void
    {
        $guild = $this->repository->insert('テストギルド', '説明文', $this->masterPlayerId);

        $this->assertSame('テストギルド', $guild->getName());
        $this->assertSame('説明文', $guild->getDescription());
        $this->assertSame(1, $guild->getLevel());
        $this->assertSame(0, $guild->getExp());
        $this->assertSame(30, $guild->getMaxMembers());
        $this->assertSame(1, $guild->getCurrentMembers(), 'マスター1人だけ');

        $this->assertDatabaseHas('sys_guild_member', [
            'sys_guild_id' => $guild->getId(),
            'sys_player_id' => $this->masterPlayerId,
            'role' => GuildRole::MASTER,
        ], 'sys');
    }

    #[Test]
    public function idで引ける(): void
    {
        $created = $this->createGuild();

        $found = $this->repository->selectById($created->getId());

        $this->assertNotNull($found);
        $this->assertSame($created->getId(), $found->getId());
        $this->assertSame('テストギルド', $found->getName());
    }

    #[Test]
    public function 存在しないidはnullを返す(): void
    {
        $this->assertNull($this->repository->selectById(999999));
    }

    #[Test]
    public function 名前で引ける(): void
    {
        $created = $this->createGuild();

        $this->assertSame($created->getId(), $this->repository->selectByName('テストギルド')?->getId());
    }

    #[Test]
    public function 存在しない名前はnullを返す(): void
    {
        $this->assertNull($this->repository->selectByName('無いギルド'));
    }

    #[Test]
    public function 全件取得できる(): void
    {
        $this->createGuild();
        $this->createGuild('ふたつめ');

        $names = array_map(fn (Guild $guild) => $guild->getName(), $this->repository->selectAll());

        $this->assertEqualsCanonicalizing(['テストギルド', 'ふたつめ'], $names);
    }

    #[Test]
    public function ギルドが無ければ全件取得は空(): void
    {
        $this->assertSame([], $this->repository->selectAll());
    }

    #[Test]
    public function 現在人数はメンバー行を数えた値になる(): void
    {
        // current_members はカラムではないので、
        // メンバーを足したら引き直すたびに増える
        $guild = $this->createGuild();

        DB::connection('sys')->table('sys_guild_member')->insert([
            'sys_guild_id' => $guild->getId(),
            'sys_player_id' => $this->masterPlayerId + 1,
            'role' => GuildRole::MEMBER,
            'joined_at' => '2026-03-15 12:00:00',
        ]);

        $this->assertSame(2, $this->repository->selectById($guild->getId())?->getCurrentMembers());
    }

    #[Test]
    public function 更新した内容がdbに届く(): void
    {
        $guild = $this->createGuild();

        $updated = $this->repository->update($guild, [
            'name' => '改名後',
            'description' => '説明も変える',
            'level' => 5,
            'exp' => 1200,
            'max_members' => 50,
        ]);
        $this->queryManager->execAllQuery();

        $this->assertSame('改名後', $updated->getName());
        $this->assertSame(50, $updated->getMaxMembers());
        $this->assertDatabaseHas('sys_guild', [
            'id' => $guild->getId(),
            'name' => '改名後',
            'description' => '説明も変える',
            'level' => 5,
            'exp' => 1200,
            'max_members' => 50,
        ], 'sys');
    }

    #[Test]
    public function 渡されなかった項目は変えない(): void
    {
        $guild = $this->repository->update($this->createGuild(), ['exp' => 300]);

        $this->assertSame('テストギルド', $guild->getName());
        $this->assertSame(1, $guild->getLevel());
        $this->assertSame(300, $guild->getExp());
    }

    #[Test]
    public function 経験値を加算できる(): void
    {
        $guild = $this->repository->addExp($this->createGuild(), 150);
        $guild = $this->repository->addExp($guild, 50);
        $this->queryManager->execAllQuery();

        $this->assertSame(200, $guild->getExp(), '加算であって上書きではない');
        $this->assertDatabaseHas('sys_guild', ['id' => $guild->getId(), 'exp' => 200], 'sys');
    }

    #[Test]
    public function レベルと経験値をまとめて更新できる(): void
    {
        $guild = $this->repository->updateLevel($this->createGuild(), 3, 40);
        $this->queryManager->execAllQuery();

        $this->assertSame(3, $guild->getLevel());
        $this->assertSame(40, $guild->getExp());
        $this->assertDatabaseHas('sys_guild', ['id' => $guild->getId(), 'level' => 3, 'exp' => 40], 'sys');
    }

    #[Test]
    public function 存在しないギルドの更新は例外になる(): void
    {
        $guild = $this->createGuild();
        $this->repository->delete($guild);
        $this->queryManager->execAllQuery();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Guild not found');

        $this->repository->update($guild, ['name' => '改名後']);
    }

    #[Test]
    public function ギルドを削除できる(): void
    {
        $guild = $this->createGuild();

        $this->repository->delete($guild);
        $this->queryManager->execAllQuery();

        $this->assertDatabaseMissing('sys_guild', ['id' => $guild->getId()], 'sys');
    }

    #[Test]
    public function 存在しないギルドの削除は何もしない(): void
    {
        $guild = $this->createGuild();
        $this->repository->delete($guild);
        $this->queryManager->execAllQuery();

        // 2回目は対象が無いので黙って終わる
        $this->repository->delete($guild);
        $this->queryManager->execAllQuery();

        $this->assertDatabaseMissing('sys_guild', ['id' => $guild->getId()], 'sys');
    }

    private function createGuild(string $name = 'テストギルド'): Guild
    {
        $guild = $this->repository->insert($name, '説明文', $this->masterPlayerId);
        $this->queryManager->execAllQuery();

        return $guild;
    }
}
