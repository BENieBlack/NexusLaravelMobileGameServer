<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;

/**
 * LogVipPoint Model
 * 
 * VIPポイント変動ログ
 * 
 * @property int $id
 * @property string $unique_request_id
 * @property int $sys_player_id
 * @property int $before_vip_level
 * @property int $after_vip_level
 * @property int $before_vip_point
 * @property int $after_vip_point
 * @property int $point_diff
 * @property string $reason
 * @property float|null $purchase_amount
 * @property string|null $currency_code
 * @property string|null $mst_in_app_purchase_id
 * @property \DateTimeImmutable $system_at
 * @property \DateTimeImmutable $created_at
 */
class LogVipPoint extends Model
{
    protected $connection = 'log';
    protected $table = 'log_vip_point';

    public $timestamps = false;

    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'before_vip_level',
        'after_vip_level',
        'before_vip_point',
        'after_vip_point',
        'point_diff',
        'reason',
        'purchase_amount',
        'currency_code',
        'mst_in_app_purchase_id',
        'system_at',
        'created_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'sys_player_id' => 'integer',
        'before_vip_level' => 'integer',
        'after_vip_level' => 'integer',
        'before_vip_point' => 'integer',
        'after_vip_point' => 'integer',
        'point_diff' => 'integer',
        'purchase_amount' => 'decimal:2',
        'system_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];

    /**
     * 一意リクエストIDを取得
     */
    public function getUniqueRequestId(): string
    {
        return $this->unique_request_id;
    }

    /**
     * プレイヤーIDを取得
     */
    public function getSysPlayerId(): int
    {
        return $this->sys_player_id;
    }

    /**
     * 変更前VIPレベルを取得
     */
    public function getBeforeVipLevel(): int
    {
        return $this->before_vip_level;
    }

    /**
     * 変更後VIPレベルを取得
     */
    public function getAfterVipLevel(): int
    {
        return $this->after_vip_level;
    }

    /**
     * 変更前VIPポイントを取得
     */
    public function getBeforeVipPoint(): int
    {
        return $this->before_vip_point;
    }

    /**
     * 変更後VIPポイントを取得
     */
    public function getAfterVipPoint(): int
    {
        return $this->after_vip_point;
    }

    /**
     * ポイント増減量を取得
     */
    public function getPointDiff(): int
    {
        return $this->point_diff;
    }

    /**
     * 変更理由を取得
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * 課金額を取得
     */
    public function getPurchaseAmount(): ?float
    {
        return $this->purchase_amount;
    }

    /**
     * 通貨コードを取得
     */
    public function getCurrencyCode(): ?string
    {
        return $this->currency_code;
    }

    /**
     * アプリ内課金マスターIDを取得
     */
    public function getMstInAppPurchaseId(): ?string
    {
        return $this->mst_in_app_purchase_id;
    }

    /**
     * システム日時を取得
     */
    public function getSystemAt(): \DateTimeImmutable
    {
        return $this->system_at;
    }
}
