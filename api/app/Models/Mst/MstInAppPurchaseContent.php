<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MstInAppPurchaseContent extends _BaseMst
{
    public $table = 'mst_in_app_purchase_content';

    protected $primaryKey = null;

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_in_app_purchase_id',
        'content_type',
        'content_mst_id',
        'content_option',
        'content_quantity',
        'amount',
        'sort_desc',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'mst_in_app_purchase_id' => 'integer',
        'content_option' => 'array',
        'content_quantity' => 'integer',
        'amount' => 'integer',
        'sort_desc' => 'integer',
    ];

    public $timestamps = true;

    /**
     * 複合主キーを使用するため、主キーの設定を無効化
     * 実際の複合主キーは: [mst_in_app_purchase_id, content_type, content_mst_id]
     */
    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('mst_in_app_purchase_id', '=', $this->getAttribute('mst_in_app_purchase_id'))
            ->where('content_type', '=', $this->getAttribute('content_type'))
            ->where('content_mst_id', '=', $this->getAttribute('content_mst_id'));

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
     * コンテンツがItemの場合のリレーション
     */
    /**
     * @return BelongsTo<MstItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MstItem::class, 'content_mst_id');
    }

    /**
     * コンテンツがUnitの場合のリレーション
     */
    /**
     * @return BelongsTo<MstUnit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(MstUnit::class, 'content_mst_id');
    }

    /**
     * デプロイキーを取得
     */
    public function getDeployKey(): int
    {
        return $this->getAttribute('deploy_key');
    }

    /**
     * アプリ内課金IDを取得
     */
    public function getMstInAppPurchaseId(): int
    {
        return $this->getAttribute('mst_in_app_purchase_id');
    }

    /**
     * コンテンツタイプを取得
     */
    public function getContentType(): string
    {
        return $this->getAttribute('content_type');
    }

    /**
     * コンテンツIDを取得
     */
    public function getContentMstId(): string
    {
        return $this->getAttribute('content_mst_id');
    }

    /**
     * コンテンツオプションを取得
     *
     * @return array<string, mixed>|null
     */
    public function getContentOption(): ?array
    {
        return $this->getAttribute('content_option');
    }

    /**
     * コンテンツ数量を取得（1配布あたり）
     */
    public function getContentQuantity(): int
    {
        return $this->getAttribute('content_quantity');
    }

    /**
     * 数量を取得（配布回数）
     */
    public function getAmount(): int
    {
        return $this->getAttribute('amount');
    }

    /**
     * 実際の配布総量を取得（content_quantity × amount）
     */
    public function getTotalQuantity(): int
    {
        return $this->getContentQuantity() * $this->getAmount();
    }

    /**
     * ソート順を取得
     */
    public function getSortDesc(): int
    {
        return $this->getAttribute('sort_desc');
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 複合主キー(mst_in_app_purchase_id, content_type, content_mst_id)のため、idフィールドは存在しない
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
