<?php

namespace App\Models\Mst;

use App\Traits\CompositePrimaryKeyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MstTradeProductL10n Model
 *
 * 交換所の商品の表示名・説明（多言語）
 *
 * @property int $deploy_key
 * @property string $mst_trade_product_id
 * @property string $language
 * @property string $name
 * @property string|null $description
 * @property string $created_at
 * @property string $updated_at
 */
class MstTradeProductL10n extends _BaseMst
{
    use CompositePrimaryKeyTrait;

    public $table = 'mst_trade_product__l10n';

    public $incrementing = false;

    protected $keyType = 'string';

    /** テーブル定義どおりの複合主キー */
    protected $primaryKey = ['mst_trade_product_id', 'language'];

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_trade_product_id',
        'language',
        'name',
        'description',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
    ];

    public $timestamps = true;

    /**
     * @return BelongsTo<MstTradeProduct, $this>
     */
    public function tradeProduct(): BelongsTo
    {
        return $this->belongsTo(MstTradeProduct::class, 'mst_trade_product_id', 'id');
    }

    public function getMstTradeProductId(): string
    {
        return (string) $this->getAttribute('mst_trade_product_id');
    }

    public function getLanguage(): string
    {
        return (string) $this->getAttribute('language');
    }

    public function getName(): string
    {
        return (string) $this->getAttribute('name');
    }

    public function getDescription(): ?string
    {
        $description = $this->getAttribute('description');

        return $description === null ? null : (string) $description;
    }
}
