<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MstTradeProduct Model
 *
 * 交換所の商品（支払うもの・コスト・交換回数の上限・交換期間）
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_trade_id
 * @property string $cost_type
 * @property string|null $cost_mst_id
 * @property int $cost_amount
 * @property int|null $trade_limit
 * @property string $reset_type
 * @property string $start_at
 * @property string|null $end_at
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class MstTradeProduct extends _BaseMst
{
    public $table = 'mst_trade_product';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'mst_trade_id',
        'cost_type',
        'cost_mst_id',
        'cost_amount',
        'trade_limit',
        'reset_type',
        'start_at',
        'end_at',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'cost_amount' => 'integer',
        'sort_order' => 'integer',
    ];

    public $timestamps = true;

    /**
     * @return HasMany<MstTradeProductL10n, $this>
     */
    public function l10n(): HasMany
    {
        return $this->hasMany(MstTradeProductL10n::class, 'mst_trade_product_id', 'id');
    }

    public function getId(): string
    {
        return (string) $this->getAttribute('id');
    }

    public function getMstTradeId(): string
    {
        return (string) $this->getAttribute('mst_trade_id');
    }

    public function getCostType(): string
    {
        return (string) $this->getAttribute('cost_type');
    }

    public function getCostMstId(): ?string
    {
        $costMstId = $this->getAttribute('cost_mst_id');

        return $costMstId === null ? null : (string) $costMstId;
    }

    public function getCostAmount(): int
    {
        return (int) $this->getAttribute('cost_amount');
    }

    /**
     * 交換回数の上限（NULLは無制限）
     */
    public function getTradeLimit(): ?int
    {
        $tradeLimit = $this->getAttribute('trade_limit');

        return $tradeLimit === null ? null : (int) $tradeLimit;
    }

    public function getResetType(): string
    {
        return (string) $this->getAttribute('reset_type');
    }

    /**
     * 交換開始日時（Y-m-d H:i:s形式）
     */
    public function getStartAt(): string
    {
        return (string) $this->getDateAttributeString('start_at');
    }

    /**
     * 交換終了日時（Y-m-d H:i:s形式、NULLは無期限）
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
