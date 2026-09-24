<?php

namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxDiamondBalance Model
 *
 * ダイヤモンド残高を購入単位で管理するモデル（FIFO方式）
 * PRIMARY KEY: id
 *
 * 先入先出（FIFO）方式で消費し、返金計算を可能にする
 *
 * platform: プラットフォーム（Apple, Google）
 * billing_platform: 決済プラットフォーム（AppStore, GooglePlay, PayPal, Stripe等）
 * current_amount: 現在の残高
 * purchase_amount: 購入時の数量
 * unit_price: 単価（返金計算用）
 */
class TrxDiamondBalance extends _BaseTrx
{
    protected $table = 'trx_diamond_balance';

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     */
    /** @var list<string> */
    protected array $selectKeys = ['sys_player_id'];

    /**
     * ユニークキー（IDで一意）
     */

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'platform',
        'billing_platform',
        'current_amount',
        'purchase_amount',
        'unit_price',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'id' => 'integer',
        'sys_player_id' => 'integer',
        'current_amount' => 'integer',
        'purchase_amount' => 'integer',
        'unit_price' => 'decimal:2',
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
     * システムプレイヤーIDを設定
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * プラットフォームを設定
     */
    public function setPlatform(string $platform): void
    {
        $this->setAttribute('platform', $platform);
    }

    /**
     * 決済プラットフォームを設定
     */
    public function setBillingPlatform(string $billingPlatform): void
    {
        $this->setAttribute('billing_platform', $billingPlatform);
    }

    /**
     * 現在残高を設定
     */
    public function setCurrentAmount(int $currentAmount): void
    {
        $this->setAttribute('current_amount', $currentAmount);
    }

    /**
     * 購入時数量を設定
     */
    public function setPurchaseAmount(int $purchaseAmount): void
    {
        $this->setAttribute('purchase_amount', $purchaseAmount);
    }

    /**
     * 単価を設定
     */
    public function setUnitPrice(float $unitPrice): void
    {
        $this->setAttribute('unit_price', $unitPrice);
    }

    /**
     * レスポンス用配列に変換
     *
     * データベース層の'id'をAPI層の'trx_diamond_balance_id'に変換
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        if (isset($array['id'])) {
            $array['trx_diamond_balance_id'] = $array['id'];
            unset($array['id']);
        }

        return $array;
    }
}
