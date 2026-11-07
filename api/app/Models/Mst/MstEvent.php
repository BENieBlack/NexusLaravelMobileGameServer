<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MstEvent Model
 *
 * イベント（期間の正本）
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $start_at
 * @property string $end_at
 * @property string $exchange_end_at
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class MstEvent extends _BaseMst
{
    public $table = 'mst_event';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'start_at',
        'end_at',
        'exchange_end_at',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'sort_order' => 'integer',
    ];

    public $timestamps = true;

    /**
     * @return HasMany<MstEventL10n, $this>
     */
    public function l10n(): HasMany
    {
        return $this->hasMany(MstEventL10n::class, 'mst_event_id', 'id');
    }

    public function getId(): string
    {
        return (string) $this->getAttribute('id');
    }

    /**
     * 開始日時（Y-m-d H:i:s形式）
     */
    public function getStartAt(): string
    {
        return (string) $this->getDateAttributeString('start_at');
    }

    /**
     * プレイ期間の終わり（Y-m-d H:i:s形式）
     */
    public function getEndAt(): string
    {
        return (string) $this->getDateAttributeString('end_at');
    }

    /**
     * 交換・受け取り期間の終わり（Y-m-d H:i:s形式）
     */
    public function getExchangeEndAt(): string
    {
        return (string) $this->getDateAttributeString('exchange_end_at');
    }

    public function getSortOrder(): int
    {
        return (int) $this->getAttribute('sort_order');
    }
}
