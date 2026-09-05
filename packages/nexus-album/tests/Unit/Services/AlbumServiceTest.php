<?php

namespace NexusAlbum\Tests\Unit\Services;

use Nexus\Core\Utilities\ClockUtility;
use NexusAlbum\DataTransferObjects\AlbumEntry;
use NexusAlbum\Enums\AlbumContentType;
use NexusAlbum\Repositories\AlbumCatalogRepositoryInterface;
use NexusAlbum\Repositories\AlbumEntryRepositoryInterface;
use NexusAlbum\Services\AlbumService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * AlbumService のユニットテスト
 *
 * 永続化とマスターはインターフェースの向こう側なので、
 * メモリ上の実装を差し込んで検証する。
 *
 * 「一度でも入手したか」を記録するだけなので、
 * 何度呼んでも初回の1件しか残らないことが要点。
 */
class AlbumServiceTest extends TestCase
{
    private const PLAYER_ID = 100;

    private FakeAlbumEntryRepository $entryRepository;

    private FakeAlbumCatalogRepository $catalogRepository;

    private AlbumService $service;

    protected function setUp(): void
    {
        parent::setUp();

        ClockUtility::setNow('2026-08-28 12:00:00');

        $this->entryRepository = new FakeAlbumEntryRepository;
        $this->catalogRepository = new FakeAlbumCatalogRepository;
        $this->service = new AlbumService($this->entryRepository, $this->catalogRepository);
    }

    protected function tearDown(): void
    {
        ClockUtility::reset();

        parent::tearDown();
    }

    #[Test]
    public function 初めての対象は記録される(): void
    {
        $unlocked = $this->service->unlock(self::PLAYER_ID, AlbumContentType::UNIT, 'unit_knight_001');

        $this->assertTrue($unlocked);
        $this->assertCount(1, $this->entryRepository->entries);

        $entry = $this->entryRepository->entries[0];
        $this->assertSame(self::PLAYER_ID, $entry->getSysPlayerId());
        $this->assertSame('unit', $entry->getContentTypeValue());
        $this->assertSame('unit_knight_001', $entry->getContentMstId());
        $this->assertSame('2026-08-28 12:00:00', $entry->getUnlockedAt());
    }

    #[Test]
    public function 二度目は記録されない(): void
    {
        $this->service->unlock(self::PLAYER_ID, AlbumContentType::UNIT, 'unit_knight_001');

        $this->assertFalse(
            $this->service->unlock(self::PLAYER_ID, AlbumContentType::UNIT, 'unit_knight_001'),
            '2回目は新規登録として扱わない'
        );
        $this->assertCount(1, $this->entryRepository->entries);
    }

    #[Test]
    public function 解放日時は初回のものが残る(): void
    {
        $this->service->unlock(self::PLAYER_ID, AlbumContentType::UNIT, 'unit_knight_001');

        ClockUtility::setNow('2026-09-01 09:00:00');
        $this->service->unlock(self::PLAYER_ID, AlbumContentType::UNIT, 'unit_knight_001');

        $this->assertSame('2026-08-28 12:00:00', $this->entryRepository->entries[0]->getUnlockedAt());
    }

    #[Test]
    public function アルバム対象でないマスターは記録しない(): void
    {
        $this->catalogRepository->targets[AlbumContentType::ITEM->value] = ['item_collectible_001'];

        $unlocked = $this->service->unlock(self::PLAYER_ID, AlbumContentType::ITEM, 'item_potion_001');

        $this->assertFalse($unlocked, '消耗品などの対象外は記録しない');
        $this->assertSame([], $this->entryRepository->entries);
    }

    #[Test]
    public function 種別が違えば別の記録になる(): void
    {
        $this->service->unlock(self::PLAYER_ID, AlbumContentType::UNIT, 'shared_id');
        $this->service->unlock(self::PLAYER_ID, AlbumContentType::ITEM, 'shared_id');

        $this->assertCount(2, $this->entryRepository->entries);
    }

    #[Test]
    public function まとめて記録できる(): void
    {
        $unlockedCount = $this->service->unlockMany(self::PLAYER_ID, [
            [AlbumContentType::UNIT, 'unit_a'],
            [AlbumContentType::UNIT, 'unit_b'],
            [AlbumContentType::UNIT, 'unit_a'],
        ]);

        $this->assertSame(2, $unlockedCount, '重複分は数えない');
        $this->assertCount(2, $this->entryRepository->entries);
    }

    #[Test]
    public function 記録済みかどうかを問い合わせられる(): void
    {
        $this->service->unlock(self::PLAYER_ID, AlbumContentType::UNIT, 'unit_knight_001');

        $this->assertTrue($this->service->isUnlocked(self::PLAYER_ID, AlbumContentType::UNIT, 'unit_knight_001'));
        $this->assertFalse($this->service->isUnlocked(self::PLAYER_ID, AlbumContentType::UNIT, 'unit_mage_002'));
    }

    #[Test]
    public function 収集状況を種別ごとに返す(): void
    {
        $this->catalogRepository->targets = [
            'unit' => ['unit_a', 'unit_b', 'unit_c', 'unit_d'],
            'equipment' => ['equip_a', 'equip_b'],
            'item' => [],
        ];

        $this->service->unlock(self::PLAYER_ID, AlbumContentType::UNIT, 'unit_a');
        $this->service->unlock(self::PLAYER_ID, AlbumContentType::EQUIPMENT, 'equip_a');
        $this->service->unlock(self::PLAYER_ID, AlbumContentType::EQUIPMENT, 'equip_b');

        $progressByType = [];
        foreach ($this->service->findProgress(self::PLAYER_ID) as $progress) {
            $progressByType[$progress->getContentTypeValue()] = $progress;
        }

        $this->assertSame(1, $progressByType['unit']->getUnlockedCount());
        $this->assertSame(4, $progressByType['unit']->getTotalCount());
        $this->assertSame(0.25, $progressByType['unit']->calcRate());
        $this->assertFalse($progressByType['unit']->isComplete());

        $this->assertTrue($progressByType['equipment']->isComplete(), '2/2は集めきり');

        // 記録が無い種別も 0 として返る
        $this->assertSame(0, $progressByType['item']->getUnlockedCount());
        $this->assertSame(0.0, $progressByType['item']->calcRate(), '対象0件でもゼロ除算しない');
        $this->assertFalse($progressByType['item']->isComplete(), '対象0件は集めきり扱いにしない');
    }

    #[Test]
    public function 記録の一覧を取得できる(): void
    {
        $this->service->unlock(self::PLAYER_ID, AlbumContentType::UNIT, 'unit_a');
        $this->service->unlock(self::PLAYER_ID, AlbumContentType::ITEM, 'item_a');

        $entries = $this->service->findEntries(self::PLAYER_ID);

        $this->assertCount(2, $entries);
        $this->assertContainsOnlyInstancesOf(AlbumEntry::class, $entries);
    }
}

/**
 * メモリ上で完結するAlbumEntryRepositoryInterface実装
 */
class FakeAlbumEntryRepository implements AlbumEntryRepositoryInterface
{
    /** @var list<AlbumEntry> */
    public array $entries = [];

    public function selectByPlayerId(int $sysPlayerId): array
    {
        return array_values(array_filter(
            $this->entries,
            fn (AlbumEntry $entry) => $entry->getSysPlayerId() === $sysPlayerId
        ));
    }

    public function exists(int $sysPlayerId, AlbumContentType $contentType, string $contentMstId): bool
    {
        foreach ($this->selectByPlayerId($sysPlayerId) as $entry) {
            if ($entry->isSameTarget($contentType, $contentMstId)) {
                return true;
            }
        }

        return false;
    }

    public function insert(AlbumEntry $albumEntry): void
    {
        $this->entries[] = $albumEntry;
    }

    public function countByType(int $sysPlayerId): array
    {
        $countByType = [];

        foreach ($this->selectByPlayerId($sysPlayerId) as $entry) {
            $contentType = $entry->getContentTypeValue();
            $countByType[$contentType] = ($countByType[$contentType] ?? 0) + 1;
        }

        return $countByType;
    }
}

/**
 * メモリ上で完結するAlbumCatalogRepositoryInterface実装
 *
 * 既定では何を渡してもアルバム対象として扱う
 */
class FakeAlbumCatalogRepository implements AlbumCatalogRepositoryInterface
{
    /** @var array<string, list<string>>|null 種別 => 対象マスターID（nullなら全て対象） */
    public ?array $targets = null;

    public function countTargetsByType(): array
    {
        if ($this->targets === null) {
            return [];
        }

        return array_map(fn (array $ids) => count($ids), $this->targets);
    }

    public function isTarget(AlbumContentType $contentType, string $contentMstId): bool
    {
        if ($this->targets === null) {
            return true;
        }

        return in_array($contentMstId, $this->targets[$contentType->value] ?? [], true);
    }
}
