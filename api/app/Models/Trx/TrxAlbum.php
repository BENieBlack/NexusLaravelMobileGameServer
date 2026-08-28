<?php

namespace App\Models\Trx;

/**
 * TrxAlbum Model
 *
 * プレイヤーが一度でも入手・解放した対象の記録
 *
 * 所持テーブル（trx_unit等）と違い、手放しても消えない。
 * (sys_player_id, content_type, content_mst_id) で一意。
 *
 * @property int $id
 * @property int $sys_player_id
 * @property string $content_type
 * @property string $content_mst_id
 * @property string $unlocked_at
 * @property bool $is_delete
 * @property string $created_at
 * @property string $updated_at
 */
class TrxAlbum extends _BaseTrx
{
    protected $table = 'trx_album';

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（プレイヤー・種別・マスターIDで一意）
     *
     * @var list<string>
     */
    protected array $uniqueKeys = ['sys_player_id', 'content_type', 'content_mst_id'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'content_type',
        'content_mst_id',
        'unlocked_at',
        'is_delete',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'is_delete' => 'boolean',
    ];

    public function getId(): int
    {
        return (int) $this->getAttribute('id');
    }

    public function getSysPlayerId(): int
    {
        return (int) $this->getAttribute('sys_player_id');
    }

    public function getContentType(): string
    {
        return (string) $this->getAttribute('content_type');
    }

    public function getContentMstId(): string
    {
        return (string) $this->getAttribute('content_mst_id');
    }

    /**
     * 解放日時を取得（Y-m-d H:i:s形式）
     */
    public function getUnlockedAt(): ?string
    {
        return $this->getDateAttributeString('unlocked_at');
    }

    public function getIsDelete(): bool
    {
        return (bool) $this->getAttribute('is_delete');
    }
}
