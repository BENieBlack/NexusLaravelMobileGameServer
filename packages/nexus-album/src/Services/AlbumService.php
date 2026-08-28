<?php

namespace NexusAlbum\Services;

use Nexus\Core\Utilities\ClockUtility;
use NexusAlbum\DataTransferObjects\AlbumEntry;
use NexusAlbum\Enums\AlbumEntryType;
use NexusAlbum\Repositories\AlbumCatalogRepositoryInterface;
use NexusAlbum\Repositories\AlbumEntryRepositoryInterface;
use NexusAlbum\ValueObjects\AlbumProgress;

/**
 * AlbumService
 *
 * アルバムへの記録と、収集状況の取得を行う
 *
 * unlock() がアルバムへの唯一の入口。
 * 入手起因（配布のHandler経由）でも進行起因（直接呼び出し）でも、
 * 必ずここを通すことで記録の形を1つに保つ。
 */
class AlbumService
{
    public function __construct(
        private readonly AlbumEntryRepositoryInterface $albumEntryRepository,
        private readonly AlbumCatalogRepositoryInterface $albumCatalogRepository,
    ) {}

    /**
     * アルバムに記録する
     *
     * 何度呼んでも初回の1件だけが残る（解放日時は初回のもの）。
     * アルバム対象外のマスターは記録しない。
     *
     * @return bool 今回新しく記録したならtrue
     */
    public function unlock(int $sysPlayerId, AlbumEntryType $type, string $masterId): bool
    {
        if (! $this->albumCatalogRepository->isTarget($type, $masterId)) {
            return false;
        }

        if ($this->albumEntryRepository->exists($sysPlayerId, $type, $masterId)) {
            return false;
        }

        $this->albumEntryRepository->insert(new AlbumEntry(
            sysPlayerId: $sysPlayerId,
            type: $type,
            masterId: $masterId,
            unlockedAt: ClockUtility::nowToString(),
        ));

        return true;
    }

    /**
     * 複数の対象をまとめて記録する
     *
     * @param  array<int, array{0: AlbumEntryType, 1: string}>  $targets  [種別, マスターID] の配列
     * @return int 新しく記録した件数
     */
    public function unlockMany(int $sysPlayerId, array $targets): int
    {
        $unlockedCount = 0;

        foreach ($targets as [$type, $masterId]) {
            if ($this->unlock($sysPlayerId, $type, $masterId)) {
                $unlockedCount++;
            }
        }

        return $unlockedCount;
    }

    /**
     * 記録済みかどうか
     */
    public function isUnlocked(int $sysPlayerId, AlbumEntryType $type, string $masterId): bool
    {
        return $this->albumEntryRepository->exists($sysPlayerId, $type, $masterId);
    }

    /**
     * プレイヤーの記録を全件取得する
     *
     * @return array<int, AlbumEntry>
     */
    public function findEntries(int $sysPlayerId): array
    {
        return $this->albumEntryRepository->selectByPlayerId($sysPlayerId);
    }

    /**
     * 種別ごとの収集状況を取得する
     *
     * 記録が1件も無い種別も 0/総数 として返す
     *
     * @return array<int, AlbumProgress>
     */
    public function findProgress(int $sysPlayerId): array
    {
        $unlockedCountByType = $this->albumEntryRepository->countByType($sysPlayerId);
        $totalCountByType = $this->albumCatalogRepository->countTargetsByType();

        $progressList = [];

        foreach (AlbumEntryType::cases() as $type) {
            $progressList[] = new AlbumProgress(
                type: $type,
                unlockedCount: $unlockedCountByType[$type->value] ?? 0,
                totalCount: $totalCountByType[$type->value] ?? 0,
            );
        }

        return $progressList;
    }
}
