<?php

namespace NexusLevel\Tests\Unit\Services;

use NexusLevel\Contracts\PlayerLevelUpHandlerInterface;
use NexusLevel\Repositories\PlayerLevelRepositoryInterface;
use NexusLevel\Services\PlayerLevelService;
use NexusPlayer\DataTransferObjects\Player;
use NexusPlayer\Repositories\PlayerRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * PlayerLevelService のユニットテスト
 *
 * 永続化はRepositoryInterfaceの向こう側なので、メモリ上の実装を差し込んで検証する。
 * レベルアップ時のゲーム固有処理はハンドラに委譲されるため、
 * ここでは「レベルが上がったときだけ呼ばれる」ことだけを確認する。
 */
class PlayerLevelServiceTest extends TestCase
{
    /** レベル => [必要累積経験値, 最大スタミナ] */
    private const LEVELS = [
        1 => [0, 50],
        2 => [100, 55],
        3 => [300, 60],
        4 => [600, 65],
    ];

    private const PLAYER_ID = 1;

    #[Test]
    public function レベル情報を取得できる(): void
    {
        $service = $this->makeService($this->makePlayerRepository(level: 2, levelExp: 150));

        $result = $service->findPlayerLevel(self::PLAYER_ID);

        $this->assertSame(2, $result['level']);
        $this->assertSame(150, $result['exp']);
        // レベル3に必要な累積300 - 現在150
        $this->assertSame(150, $result['exp_to_next']);
        $this->assertSame(55, $result['max_stamina']);
    }

    #[Test]
    public function 存在しないプレイヤーは例外になる(): void
    {
        $service = $this->makeService($this->makePlayerRepository(level: 1, levelExp: 0));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Player not found: 999');

        $service->findPlayerLevel(999);
    }

    #[Test]
    public function 経験値を加算してもレベルが上がらない場合がある(): void
    {
        $players = $this->makePlayerRepository(level: 1, levelExp: 0);
        $handler = new RecordingLevelUpHandler;
        $service = $this->makeService($players, $handler);

        $result = $service->addExp(self::PLAYER_ID, 50);

        $this->assertFalse($result['is_leveled_up']);
        $this->assertSame(1, $result['after_level']);
        $this->assertSame(50, $result['total_exp']);
        $this->assertSame(50, $result['exp_to_next']);
        $this->assertSame(50, $result['before_max_stamina']);
        $this->assertSame(50, $result['after_max_stamina']);

        // レベルが上がっていないのでゲーム固有処理は呼ばれない
        $this->assertSame([], $handler->calls);
    }

    #[Test]
    public function レベルアップすると更新が永続化されハンドラが呼ばれる(): void
    {
        $players = $this->makePlayerRepository(level: 1, levelExp: 0);
        $handler = new RecordingLevelUpHandler;
        $service = $this->makeService($players, $handler);

        $result = $service->addExp(self::PLAYER_ID, 100);

        $this->assertTrue($result['is_leveled_up']);
        $this->assertSame(1, $result['before_level']);
        $this->assertSame(2, $result['after_level']);
        $this->assertSame(100, $result['total_exp']);
        $this->assertSame(50, $result['before_max_stamina']);
        $this->assertSame(55, $result['after_max_stamina']);

        // 永続化された値
        $this->assertSame([[self::PLAYER_ID, 2, 100]], $players->persisted);

        // レベルアップ前後のレベルがそのまま渡る
        $this->assertSame([[self::PLAYER_ID, 1, 2]], $handler->calls);
    }

    #[Test]
    public function 一度に複数レベル上がる(): void
    {
        $players = $this->makePlayerRepository(level: 1, levelExp: 0);
        $handler = new RecordingLevelUpHandler;
        $service = $this->makeService($players, $handler);

        $result = $service->addExp(self::PLAYER_ID, 600);

        $this->assertSame(4, $result['after_level']);
        $this->assertSame(65, $result['after_max_stamina']);
        // 最大レベルのため次のレベルまでの必要経験値は0
        $this->assertSame(0, $result['exp_to_next']);
        $this->assertSame([[self::PLAYER_ID, 1, 4]], $handler->calls);
    }

    #[Test]
    public function 最大レベルを超えて上がらない(): void
    {
        $players = $this->makePlayerRepository(level: 4, levelExp: 600);
        $service = $this->makeService($players);

        $result = $service->addExp(self::PLAYER_ID, 100000);

        $this->assertFalse($result['is_leveled_up']);
        $this->assertSame(4, $result['after_level']);
        // 経験値自体は累積される
        $this->assertSame(100600, $result['total_exp']);
        $this->assertSame([[self::PLAYER_ID, 4, 100600]], $players->persisted);
    }

    #[Test]
    public function ハンドラが未登録でもレベルアップできる(): void
    {
        $players = $this->makePlayerRepository(level: 1, levelExp: 0);
        $service = $this->makeService($players);

        $result = $service->addExp(self::PLAYER_ID, 100);

        $this->assertTrue($result['is_leveled_up']);
        $this->assertSame([[self::PLAYER_ID, 2, 100]], $players->persisted);
    }

    #[Test]
    public function 最大スタミナを取得できる(): void
    {
        $service = $this->makeService($this->makePlayerRepository(level: 3, levelExp: 300));

        $this->assertSame(60, $service->findMaxStamina(self::PLAYER_ID));
    }

    #[Test]
    public function マスターに最大スタミナが無ければ既定値を返す(): void
    {
        $players = $this->makePlayerRepository(level: 1, levelExp: 0);
        $service = new PlayerLevelService($players, new FakePlayerLevelRepository([]));

        $this->assertSame(50, $service->findMaxStamina(self::PLAYER_ID));
    }

    #[Test]
    public function 累積経験値からレベルを計算できる(): void
    {
        $service = $this->makeService($this->makePlayerRepository(level: 1, levelExp: 0));

        $this->assertSame(1, $service->calculateLevelFromExp(0));
        $this->assertSame(1, $service->calculateLevelFromExp(99));
        $this->assertSame(2, $service->calculateLevelFromExp(100));
        $this->assertSame(3, $service->calculateLevelFromExp(599));
        $this->assertSame(4, $service->calculateLevelFromExp(10000));
    }

    #[Test]
    public function 次のレベルまでの必要経験値を計算できる(): void
    {
        $service = $this->makeService($this->makePlayerRepository(level: 1, levelExp: 0));

        $this->assertSame(100, $service->calcExpToNextLevel(null, 1, 0));
        $this->assertSame(50, $service->calcExpToNextLevel(null, 1, 50));
        // 最大レベルでは0
        $this->assertSame(0, $service->calcExpToNextLevel(null, 4, 600));
    }

    private function makeService(
        FakePlayerRepository $players,
        ?PlayerLevelUpHandlerInterface $handler = null
    ): PlayerLevelService {
        return new PlayerLevelService($players, new FakePlayerLevelRepository(self::LEVELS), $handler);
    }

    private function makePlayerRepository(int $level, int $levelExp): FakePlayerRepository
    {
        return new FakePlayerRepository(new Player(
            id: self::PLAYER_ID,
            uuid: 'test-uuid',
            myId: 'TEST0001',
            name: 'tester',
            level: $level,
            levelExp: $levelExp,
            createdAt: '2026-01-01 00:00:00',
            updatedAt: '2026-01-01 00:00:00',
        ));
    }
}

/**
 * 1人のプレイヤーだけをメモリに持つPlayerRepositoryInterface実装
 */
class FakePlayerRepository implements PlayerRepositoryInterface
{
    /** @var list<array{0: int, 1: int, 2: int}> persist されたID・レベル・経験値 */
    public array $persisted = [];

    public function __construct(private Player $player) {}

    public function selectById(int $id): ?Player
    {
        return $id === $this->player->getId() ? $this->player : null;
    }

    public function selectByMyId(string $myId): ?Player
    {
        return $myId === $this->player->getMyId() ? $this->player : null;
    }

    public function selectByUuid(string $uuid): ?Player
    {
        return $uuid === $this->player->getUuid() ? $this->player : null;
    }

    public function persist(Player $player): void
    {
        $this->player = $player;
        $this->persisted[] = [$player->getId(), $player->getLevel(), $player->getLevelExp()];
    }

    public function existsByMyId(string $myId): bool
    {
        return $myId === $this->player->getMyId();
    }
}

/**
 * レベル定義を配列で持つPlayerLevelRepositoryInterface実装
 */
class FakePlayerLevelRepository implements PlayerLevelRepositoryInterface
{
    /**
     * @param  array<int, array{0: int, 1: int}>  $levels レベル => [必要累積経験値, 最大スタミナ]
     */
    public function __construct(private readonly array $levels) {}

    public function selectByLevel(int $level): ?array
    {
        if (! isset($this->levels[$level])) {
            return null;
        }

        [$requiredExp, $maxStamina] = $this->levels[$level];

        return [
            'level' => $level,
            'required_exp' => $requiredExp,
            'max_stamina' => $maxStamina,
        ];
    }

    public function calculateLevelFromExp(int $exp): int
    {
        $result = 1;

        foreach ($this->levels as $level => [$requiredExp, $maxStamina]) {
            if ($exp >= $requiredExp) {
                $result = $level;
            }
        }

        return $result;
    }

    public function selectMaxLevel(): int
    {
        return $this->levels === [] ? 1 : max(array_keys($this->levels));
    }

    public function findMaxStaminaForLevel(int $level): ?int
    {
        return $this->levels[$level][1] ?? null;
    }
}

/**
 * 呼び出しを記録するだけのレベルアップハンドラ
 */
class RecordingLevelUpHandler implements PlayerLevelUpHandlerInterface
{
    /** @var list<array{0: int, 1: int, 2: int}> */
    public array $calls = [];

    public function handle(int $sysPlayerId, int $beforeLevel, int $afterLevel): void
    {
        $this->calls[] = [$sysPlayerId, $beforeLevel, $afterLevel];
    }
}
