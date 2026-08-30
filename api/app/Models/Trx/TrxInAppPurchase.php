<?php

namespace App\Models\Trx;

use App\Traits\CompositePrimaryKeyTrait;
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
 *
 * @property int $purchase_count
 */
class TrxInAppPurchase extends _BaseTrx
{
    use CompositePrimaryKeyTrait;

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
    /** @var list<string> */
    protected array $selectKeys = ['sys_player_id'];

    /**
     * ユニークキー（課金商品は複合キーで一意）
     */

    /** @var list<string> */
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

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'total_purchase_count' => 'integer',
        'purchase_count' => 'integer',
    ];

    /**
     * trx_playerとのリレーション
     */
    /**
     * @return BelongsTo<TrxPlayer, $this>
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * 購入回数リセット日時を取得
     */
    public function getPurchaseCountResetAt(): ?string
    {
        return $this->getDateAttributeString('purchase_count_reset_at');
    }

    /**
     * 購入回数リセット日時を設定
     */
    public function setPurchaseCountResetAt(?string $purchaseCountResetAt): void
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
     * 累計購入回数を取得
     */
    public function getTotalPurchaseCount(): int
    {
        return (int) $this->getAttribute('total_purchase_count');
    }

    /**
     * 期間内購入回数を取得
     */
    public function getPurchaseCount(): int
    {
        return (int) $this->getAttribute('purchase_count');
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
