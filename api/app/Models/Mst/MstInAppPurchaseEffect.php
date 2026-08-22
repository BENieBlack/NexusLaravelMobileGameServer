<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MstInAppPurchaseEffect extends _BaseMst
{
    public $table = 'mst_in_app_purchase_effect';

    protected $primaryKey = null;

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_in_app_purchase_id',
        'effect_type',
        'value',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'mst_in_app_purchase_id' => 'integer',
        // effect_type は enum の文字列、value は decimal(10,2)。
        // integerにすると 'ExpBoost' → '0'、1.50 → 1 に潰れる
        'effect_type' => 'string',
        'value' => 'decimal:2',
    ];

    public $timestamps = true;

    /**
     * 複合主キーを使用するため、主キーの設定を無効化
     * 実際の複合主キーは: [mst_in_app_purchase_id, effect_type]
     */
    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('mst_in_app_purchase_id', '=', $this->getAttribute('mst_in_app_purchase_id'))
            ->where('effect_type', '=', $this->getAttribute('effect_type'));

        return $query;
    }

    /**
     * 親のアプリ内課金商品
     */
    /**
     * @return BelongsTo<MstInAppPurchase, $this>
     */
    public function inAppPurchase(): BelongsTo
    {
        return $this->belongsTo(MstInAppPurchase::class, 'mst_in_app_purchase_id');
    }

    /**
     * 効果タイプを取得
     */
    public function getEffectType(): string
    {
        return $this->getAttribute('effect_type');
    }

    /**
     * 効果値を取得
     */
    public function getValue(): float
    {
        return (float) $this->getAttribute('value');
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 複合主キー(mst_in_app_purchase_id, effect_type)のため、idフィールドは存在しない
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
