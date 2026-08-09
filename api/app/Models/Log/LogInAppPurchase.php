<?php

namespace App\Models\Log;

class LogInAppPurchase extends _BaseLog
{
    protected $table = 'log_in_app_purchase';

    protected $casts = [
        'id' => 'integer',
        'unique_request_id' => 'string',
        'sys_player_id' => 'integer',
        'platform' => 'string',
        'billing_platform' => 'string',
        'receipt_id' => 'string',
        'receipt' => 'array',
        'status' => 'string',
        'mst_in_app_purchase_id' => 'string',
        'currency_code' => 'string',
        'pay_amount' => 'decimal:2',
        'pay_string' => 'string',
    ];

    protected $fillable = [
        'unique_request_id',
        'sys_player_id',
        'platform',
        'billing_platform',
        'receipt_id',
        'receipt',
        'status',
        'mst_in_app_purchase_id',
        'currency_code',
        'pay_amount',
        'pay_string',
        'system_at',
    ];

    /**
     * プラットフォーム定数
     */
    public const PLATFORM_APPLE = 'apple';

    public const PLATFORM_GOOGLE = 'google';

    /**
     * 利用可能なプラットフォーム一覧を取得
     */
    public static function getAvailablePlatforms(): array
    {
        return [
            self::PLATFORM_APPLE,
            self::PLATFORM_GOOGLE,
        ];
    }

    /**
     * Appleプラットフォームかチェック
     */
    public function isApple(): bool
    {
        return $this->platform === self::PLATFORM_APPLE;
    }

    /**
     * Googleプラットフォームかチェック
     */
    public function isGoogle(): bool
    {
        return $this->platform === self::PLATFORM_GOOGLE;
    }
}
