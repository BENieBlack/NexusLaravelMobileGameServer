<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Languages
    |--------------------------------------------------------------------------
    |
    | サポートする言語コードの配列。
    | マイグレーションファイルやアプリケーション内で使用されます。
    |
    | - ISO 639-1 (2文字言語コード): ja, en, hi, es, fr, ar, id, pt, bn, ru, de, ko
    | - BCP 47 (言語-地域): zh-TW, zh-CN
    |
    */

    'supported' => [
        'ja',      // 日本語 (Japanese)
        'en',      // 英語 (English)
        'zh-TW',   // 繁体字中国語 (Traditional Chinese - Taiwan)
        'zh-CN',   // 簡体字中国語 (Simplified Chinese - China)
        'hi',      // ヒンディー語 (Hindi)
        'es',      // スペイン語 (Spanish)
        'fr',      // フランス語 (French)
        'ar',      // アラビア語 (Arabic)
        'id',      // インドネシア語・マレー語 (Indonesian/Malay)
        'pt',      // ポルトガル語 (Portuguese)
        'bn',      // ベンガル語 (Bengali)
        'ru',      // ロシア語 (Russian)
        'de',      // ドイツ語 (German)
        'ko',      // 韓国語 (Korean)
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Language
    |--------------------------------------------------------------------------
    |
    | デフォルトの言語コード。
    |
    */

    'default' => 'ja',

];
