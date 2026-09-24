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

    /*
    |--------------------------------------------------------------------------
    | Lazy Migration
    |--------------------------------------------------------------------------
    |
    | mst_item.is_wallet を後から立てたとき、trx_item に残っている残高を
    | プレイヤーが触った時点で Wallet へ移すかどうか。
    |
    | true（既定）: 触られた分から順に移る。切り替えにメンテナンスが要らない。
    | false: 移すのは wallet:migrate-items だけになる。移し終えるまで
    |        プレイヤーからは残高が消えて見えるため、メンテナンス中に流すこと。
    |
    | 読み取りの中で書き込みが走るのを避けたい場合に false にする。
    |
    */
    'lazy_migration' => env('WALLET_LAZY_MIGRATION', true),
];
