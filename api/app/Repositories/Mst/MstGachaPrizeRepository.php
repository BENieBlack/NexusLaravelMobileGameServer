<?php

namespace App\Repositories\Mst;

use App\Models\Mst\MstGachaPrize;
use Nexus\Core\Support\CustomCollection;
use NexusGacha\DataTransferObjects\PrizeCandidate;
use NexusGacha\Repositories\GachaPrizeRepositoryInterface;

/**
 * MstGachaPrizeRepository
 *
 * @extends _BaseMstRepository<MstGachaPrize>
 */
class MstGachaPrizeRepository extends _BaseMstRepository implements GachaPrizeRepositoryInterface
{
    protected string $modelClass = MstGachaPrize::class;

    /**
     * {@inheritDoc}
     */
    public function selectByGachaIdAndRarity(string $mstGachaId, int $rarity, bool $pickupOnly): array
    {
        return $this->selectListByGachaIdAndRarity($mstGachaId, $rarity, $pickupOnly)
            ->map(fn (MstGachaPrize $prize) => new PrizeCandidate(
                contentType: $prize->getContentType(),
                contentMstId: $prize->getContentMstId(),
                contentOption: $prize->getContentOption(),
                amount: $prize->getAmount(),
                weight: $prize->weight,
            ))
            ->values()
            ->all();
    }

    /**
     * ガチャIDとレアリティで景品リストを取得
     *
     * @param  bool  $pickupOnly  ピックアップのみ取得
     * @return CustomCollection<int, MstGachaPrize>
     */
    public function selectListByGachaIdAndRarity(
        string $mstGachaId,
        int $rarity,
        bool $pickupOnly = false
    ): CustomCollection {
        $this->queryOrMemory();

        $query = $this->models
            ->where('mst_gacha_id', $mstGachaId)
            ->where('rarity', $rarity)
            ->where('is_active', true);

        if ($pickupOnly) {
            $query = $query->where('is_pickup', true);
        }

        return $query->values();
    }
}
