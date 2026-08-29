<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxAlbum;
use Nexus\Core\Support\CustomCollection;

/**
 * TrxAlbumRepository
 *
 * アルバム記録の永続化のみを担当
 * ビジネスロジックは NexusAlbum\Services\AlbumService に実装
 *
 * @extends _BaseTrxRepository<TrxAlbum>
 */
class TrxAlbumRepository extends _BaseTrxRepository
{
    protected string $modelClass = TrxAlbum::class;

    /**
     * プレイヤーの記録を全件取得する
     *
     * @return CustomCollection<array-key, TrxAlbum>
     */
    public function selectAllByPlayer(): CustomCollection
    {
        return $this->queryOrMemory();
    }

    /**
     * 種別とマスターIDで1件取得する
     */
    public function selectByContentTypeAndMstId(string $contentType, string $contentMstId): ?TrxAlbum
    {
        /** @var TrxAlbum|null */
        return $this->queryOrMemory()
            ->where('content_type', $contentType)
            ->firstWhere('content_mst_id', $contentMstId);
    }

    /**
     * 記録を1件追加する（キューに積むだけ。書き込みはQueryManager）
     */
    public function insertEntry(int $sysPlayerId, string $contentType, string $contentMstId, string $unlockedAt): TrxAlbum
    {
        $trxAlbum = new TrxAlbum([
            'sys_player_id' => $sysPlayerId,
            'content_type' => $contentType,
            'content_mst_id' => $contentMstId,
            'unlocked_at' => $unlockedAt,
            'is_delete' => false,
        ]);
        $trxAlbum->exists = false;

        $this->setModel($trxAlbum);

        return $trxAlbum;
    }
}
