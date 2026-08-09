<?php

namespace App\Models\Trx;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxInAppPurchase Model
 *
 * 課金購入履歴を管理するモデル
 * PRIMARY KEY: (sys_player_id, billing_platform, mst_in_app_purchase_id)
 *
 * billing_platform: 課金プラットフォーム（apple, google）
 * purchase_count: 期間内購入回数（リセット可能）
 * total_purchase_count: 累計購入回数（リセットされない）
 * purchase_count_reset_at: 購入回数リセット日時
 */
class TrxInAppPurchase extends _BaseTrx
{
    protected $table = 'trx_in_app_purchase';

    /**
     * 複合主キーのため、auto incrementを無効化
     */
    public $incrementing = false;

    /**
     * 複合主キーの指定
     */
    protected $primaryKey = ['sys_player_id', 'billing_platform', 'mst_in_app_purchase_id'];

    /**
     * 主キーの型
     */
    protected $keyType = 'string';

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（課金商品は複合キーで一意）
     */
    protected array $uniqueKeys = ['sys_player_id', 'billing_platform', 'mst_in_app_purchase_id'];

    protected $fillable = [
        'sys_player_id',
        'billing_platform',
        'mst_in_app_purchase_id',
        'transaction_id',
        'total_purchase_count',
        'purchase_count',
        'purchase_count_reset_at',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'sys_player_id' => 'integer',
        'total_purchase_count' => 'integer',
        'purchase_count' => 'integer',
    ];

    /**
     * 複合主キーを設定
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (! is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * 複合主キーの値を取得
     *
     * @param  string|null  $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

    /**
     * trx_playerとのリレーション
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * 購入回数リセット日時を取得
     */
    public function getPurchaseCountResetAt(): ?CarbonImmutable
    {
        return $this->getAttribute('purchase_count_reset_at');
    }

    /**
     * 購入回数リセット日時を設定
     */
    public function setPurchaseCountResetAt(?CarbonImmutable $purchaseCountResetAt): void
    {
        $this->setAttribute('purchase_count_reset_at', $purchaseCountResetAt);
    }

    /**
     * システムプレイヤーIDを設定
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * 決済プラットフォームを設定
     */
    public function setBillingPlatform(string $billingPlatform): void
    {
        $this->setAttribute('billing_platform', $billingPlatform);
    }

    /**
     * マスター課金商品IDを設定
     */
    public function setMstInAppPurchaseId(int $mstInAppPurchaseId): void
    {
        $this->setAttribute('mst_in_app_purchase_id', $mstInAppPurchaseId);
    }

    /**
     * 累計購入回数を設定
     */
    public function setTotalPurchaseCount(int $totalPurchaseCount): void
    {
        $this->setAttribute('total_purchase_count', $totalPurchaseCount);
    }

    /**
     * 期間内購入回数を設定
     */
    public function setPurchaseCount(int $purchaseCount): void
    {
        $this->setAttribute('purchase_count', $purchaseCount);
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 複合主キー(sys_player_id, billing_platform, mst_in_app_purchase_id)のため、idフィールドは存在しない
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
