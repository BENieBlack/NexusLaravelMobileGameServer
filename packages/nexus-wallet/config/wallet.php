<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Wallet Configuration
    |--------------------------------------------------------------------------
    |
    | Virtual currency wallet management settings
    |
    */

    // デフォルトの通貨有効期限（日数、nullの場合は無期限）
    'default_expiration_days' => env('WALLET_DEFAULT_EXPIRATION_DAYS', null),

    // FIFO消費時の有償通貨優先設定
    'paid_currency_priority' => env('WALLET_PAID_CURRENCY_PRIORITY', true),

    // 有効期限切れ通貨の自動削除設定
    'auto_remove_expired' => env('WALLET_AUTO_REMOVE_EXPIRED', true),
];
