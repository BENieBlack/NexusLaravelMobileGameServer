<?php

namespace App\Repositories\Mst;

use App\Models\Mst\_BaseMst;
use Nexus\Core\Support\CustomCollection;
use NexusAlbum\Enums\AlbumEntryType;
use NexusAlbum\Repositories\AlbumCatalogRepositoryInterface;

/**
 * AlbumCatalogRepositoryAdapter
 *
 * アルバムに載る対象の総数と、対象かどうかの判定をマスターから答える
 *
 * 何を載せるかはマスターの is_album_target で決める。
 * 例えばアイテムは回復薬のような消耗品も含むため、全件を対象にすると
 * 収集率が意味を持たなくなる。
 */
class AlbumCatalogRepositoryAdapter implements AlbumCatalogRepositoryInterface
{
    public function __construct(
        private readonly MstUnitRepository $mstUnitRepository,
        private readonly MstEquipmentRepository $mstEquipmentRepository,
        private readonly MstItemRepository $mstItemRepository,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function countTargetsByType(): array
    {
        $countByType = [];

        foreach (AlbumEntryType::cases() as $type) {
            $countByType[$type->value] = $this->findTargets($type)->count();
        }

        return $countByType;
    }

    /**
     * {@inheritDoc}
     */
    public function isTarget(AlbumEntryType $type, string $masterId): bool
    {
        return $this->findTargets($type)
            ->contains(fn (_BaseMst $master) => (string) $master->getAttribute('id') === $masterId);
    }

    /**
     * 種別ごとのアルバム対象マスターを取得する
     *
     * @return CustomCollection<array-key, _BaseMst>
     */
    private function findTargets(AlbumEntryType $type): CustomCollection
    {
        $repository = match ($type) {
            AlbumEntryType::UNIT => $this->mstUnitRepository,
            AlbumEntryType::EQUIPMENT => $this->mstEquipmentRepository,
            AlbumEntryType::ITEM => $this->mstItemRepository,
        };

        /** @var CustomCollection<array-key, _BaseMst> $targets */
        $targets = $repository->queryOrMemory()->where('is_album_target', true);

        return $targets;
    }
}
