<?php

/**
 * データ引き継ぎ（機種変更）の設定
 *
 * - 引き継ぎコード: 旧端末で発行したコードとパスワードを新端末で入力する
 * - 外部アカウント: Apple / Google のアカウントを紐付けておき、新端末で同じアカウントでサインインする
 */

/**
 * カンマ区切りの環境変数を配列にする（空なら空配列）
 *
 * @return list<string>
 */
$csv = static fn (?string $value): array => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

return [
    'transfer_code' => [
        // 発行から使えなくなるまでの日数
        'expire_days' => (int) env('TRANSFER_CODE_EXPIRE_DAYS', 30),
        // パスワードをこの回数続けて間違えたらロックする
        'max_failed_attempts' => (int) env('TRANSFER_CODE_MAX_FAILED_ATTEMPTS', 5),
        // ロックする分数
        'lock_minutes' => (int) env('TRANSFER_CODE_LOCK_MINUTES', 30),
    ],

    // IDトークンの検証設定。audiences（アプリのクライアントID）が空の種類は使えない
    'external_providers' => [
        'apple' => [
            'jwks_uri' => 'https://appleid.apple.com/auth/keys',
            'issuers' => ['https://appleid.apple.com'],
            // iOS アプリのバンドルID（Web で使う場合は Services ID も）
            'audiences' => $csv(env('APPLE_CLIENT_IDS')),
        ],
        'google' => [
            'jwks_uri' => 'https://www.googleapis.com/oauth2/v3/certs',
            'issuers' => ['https://accounts.google.com', 'accounts.google.com'],
            // Google Cloud の OAuth クライアントID（iOS / Android / Web）
            'audiences' => $csv(env('GOOGLE_CLIENT_IDS')),
        ],
    ],

    // 公開鍵セット（JWKS）をキャッシュする秒数
    'jwks_cache_seconds' => (int) env('EXTERNAL_JWKS_CACHE_SECONDS', 3600),
];
