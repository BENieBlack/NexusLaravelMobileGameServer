<?php

namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxInAppPurchaseEffect Model
 * 
 * Pass課金の効果を管理するモデル
 * PRIMARY KEY: id (auto increment)
 * 
 * 仕様：同じ効果を複数回購入可能（効果のスタック/累積）
 * - 例：ExpBoostを2回購入 → 2つのレコードが作成され、効果が累積
 * 
 * effect_type: 効果タイプ（IdleRewardMultiplier, AdSkip等）
 * value: 効果値（倍率や数値）
 * expires_at: 効果の有効期限
 * is_active: 有効フラグ（手動で無効化可能）
 */
class TrxInAppPurchaseEffect extends _BaseTrx
{
    protected $table = 'trx_in_app_purchase_effect';

    /**
     * 主キー：id (auto increment)
     */
    protected $primaryKey = 'id';

    /**
     * auto incrementを有効化
     */
    public $incrementing = true;

    /**
     * 主キーの型
     */
    protected $keyType = 'int';

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     * 
     * @var string
     */
    protected string $selectKey = 'sys_player_id';

    protected $fillable = [
        'sys_player_id',
        'mst_in_app_purchase_id',
        'effect_type',
        'value',
        'expires_at',
        'is_active',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'sys_player_id' => 'integer',
        'mst_in_app_purchase_id' => 'integer',
        'value' => 'decimal:2',
        'expires_at' => 'immutable_datetime',
        'is_active' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /**
     * trx_playerとのリレーション
     *
     * @return BelongsTo
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * trx_in_app_purchaseとのリレーション
     *
     * @return BelongsTo
     */
    public function trxInAppPurchase(): BelongsTo
    {
        return $this->belongsTo(TrxInAppPurchase::class, ['sys_player_id', 'mst_in_app_purchase_id'], ['sys_player_id', 'mst_in_app_purchase_id']);
    }

    /**
     * 効果が有効かチェック
     * 
     * @return bool
     */
    public function isEffective(): bool
    {
        return $this->getIsActive() && $this->getExpiresAt()->isFuture();
    }

    /**
     * 有効フラグを取得
     *
     * @return bool
     */
    public function getIsActive(): bool
    {
        return (bool)$this->getAttribute('is_active');
    }

    /**
     * 有効フラグを設定
     *
     * @param bool $isActive
     * @return void
     */
    public function setIsActive(bool $isActive): void
    {
        $this->setAttribute('is_active', $isActive);
    }

    /**
     * 有効期限を取得
     *
     * @return \Carbon\CarbonImmutable
     */
    public function getExpiresAt(): \Carbon\CarbonImmutable
    {
        return $this->getAttribute('expires_at');
    }

    /**
     * 有効期限を設定
     *
     * @param \Carbon\CarbonImmutable $expiresAt
     * @return void
     */
    public function setExpiresAt(\Carbon\CarbonImmutable $expiresAt): void
    {
        $this->setAttribute('expires_at', $expiresAt);
    }

    /**
     * システムプレイヤーIDを設定
     *
     * @param int $sysPlayerId
     * @return void
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * マスター課金商品IDを設定
     *
     * @param int $mstInAppPurchaseId
     * @return void
     */
    public function setMstInAppPurchaseId(int $mstInAppPurchaseId): void
    {
        $this->setAttribute('mst_in_app_purchase_id', $mstInAppPurchaseId);
    }

    /**
     * 効果タイプを設定
     *
     * @param string $effectType
     * @return void
     */
    public function setEffectType(string $effectType): void
    {
        $this->setAttribute('effect_type', $effectType);
    }

    /**
     * 効果値を設定
     *
     * @param float $value
     * @return void
     */
    public function setValue(float $value): void
    {
        $this->setAttribute('value', $value);
    }

    /**
     * 削除フラグを設定
     *
     * @param bool $isDelete
     * @return void
     */
    public function setIsDelete(bool $isDelete): void
    {
        $this->setAttribute('is_delete', $isDelete);
    }

    /**
     * レスポンス用配列に変換
     * 
     * データベース層の'id'をAPI層の'trx_in_app_purchase_effect_id'に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();
        
        if (isset($array['id'])) {
            $array['trx_in_app_purchase_effect_id'] = $array['id'];
            unset($array['id']);
        }
        
        return $array;
    }
}
