<?php

namespace App\Repositories\Mst;

use App\Models\Mst\_BaseMst;
use Nexus\Core\Support\CustomCollection;
use NexusAlbum\Enums\AlbumContentType;
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

        foreach (AlbumContentType::cases() as $contentType) {
            $countByType[$contentType->value] = $this->findTargets($contentType)->count();
        }

        return $countByType;
    }

    /**
     * {@inheritDoc}
     */
    public function isTarget(AlbumContentType $contentType, string $contentMstId): bool
    {
        return $this->findTargets($contentType)
            ->contains(fn (_BaseMst $master) => (string) $master->getAttribute('id') === $contentMstId);
    }

    /**
     * 種別ごとのアルバム対象マスターを取得する
     *
     * @return CustomCollection<array-key, _BaseMst>
     */
    private function findTargets(AlbumContentType $contentType): CustomCollection
    {
        $repository = match ($contentType) {
            AlbumContentType::UNIT => $this->mstUnitRepository,
            AlbumContentType::EQUIPMENT => $this->mstEquipmentRepository,
            AlbumContentType::ITEM => $this->mstItemRepository,
        };

        /** @var CustomCollection<array-key, _BaseMst> $targets */
        $targets = $repository->queryOrMemory()->where('is_album_target', true);

        return $targets;
    }
}
