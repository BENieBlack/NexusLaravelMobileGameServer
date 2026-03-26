<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MstInAppPurchaseContent extends _BaseMst
{
    public $table = 'mst_in_app_purchase_content';

    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = [
        'deploy_key',
        'mst_in_app_purchase_id',
        'content_type',
        'content_id',
        'amount',
        'sort_desc',
    ];

    protected $casts = [
        'deploy_key' => 'integer',
        'mst_in_app_purchase_id' => 'integer',
        'amount' => 'integer',
        'sort_desc' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;

    /**
     * 複合主キーを使用するため、主キーの設定を無効化
     * 実際の複合主キーは: [mst_in_app_purchase_id, content_type, content_id]
     */
    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('mst_in_app_purchase_id', '=', $this->getAttribute('mst_in_app_purchase_id'))
            ->where('content_type', '=', $this->getAttribute('content_type'))
            ->where('content_id', '=', $this->getAttribute('content_id'));

        return $query;
    }

    /**
     * 親のアプリ内課金商品
     *
     * @return BelongsTo
     */
    public function inAppPurchase(): BelongsTo
    {
        return $this->belongsTo(MstInAppPurchase::class, 'mst_in_app_purchase_id');
    }

    /**
     * コンテンツがItemの場合のリレーション
     *
     * @return BelongsTo
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MstItem::class, 'content_id');
    }

    /**
     * コンテンツがUnitの場合のリレーション
     *
     * @return BelongsTo
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(MstUnit::class, 'content_id');
    }

    /**
     * デプロイキーを取得
     *
     * @return int
     */
    public function getDeployKey(): int
    {
        return $this->getAttribute('deploy_key');
    }

    /**
     * アプリ内課金IDを取得
     *
     * @return int
     */
    public function getMstInAppPurchaseId(): int
    {
        return $this->getAttribute('mst_in_app_purchase_id');
    }

    /**
     * コンテンツタイプを取得
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->getAttribute('content_type');
    }

    /**
     * コンテンツIDを取得
     *
     * @return string
     */
    public function getContentId(): string
    {
        return $this->getAttribute('content_id');
    }

    /**
     * 数量を取得
     *
     * @return int
     */
    public function getAmount(): int
    {
        return $this->getAttribute('amount');
    }

    /**
     * ソート順を取得
     *
     * @return int
     */
    public function getSortDesc(): int
    {
        return $this->getAttribute('sort_desc');
    }

    /**
     * レスポンス用配列に変換
     * 
     * Note: 複合主キー(mst_in_app_purchase_id, content_type, content_id)のため、idフィールドは存在しない
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
