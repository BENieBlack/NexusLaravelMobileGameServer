<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MstTrade Model
 *
 * 交換所（通常交換所、イベント交換所など）
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $start_at
 * @property string|null $end_at
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class MstTrade extends _BaseMst
{
    public $table = 'mst_trade';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'start_at',
        'end_at',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'sort_order' => 'integer',
    ];

    public $timestamps = true;

    /**
     * @return HasMany<MstTradeL10n, $this>
     */
    public function l10n(): HasMany
    {
        return $this->hasMany(MstTradeL10n::class, 'mst_trade_id', 'id');
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
     * 終了日時（Y-m-d H:i:s形式、NULLは無期限）
     */
    public function getEndAt(): ?string
    {
        return $this->getDateAttributeString('end_at');
    }

    public function getSortOrder(): int
    {
        return (int) $this->getAttribute('sort_order');
    }
}
