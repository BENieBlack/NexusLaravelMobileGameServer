<?php

namespace NexusBilling\ApiClients;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NexusBilling\Exceptions\PlatformApiException;

/**
 * Apple App Store API クライアント
 * 
 * App Store のレシート検証APIとの通信を担当
 */
class AppStoreApiClient
{
    /**
     * エンドポイント（環境 => URL）
     *
     * 接続先は services.app_store.environment（APP_STORE_ENVIRONMENT）で切り替える。
     *
     * @var array<string, array<string, string>>
     */
    private const ENDPOINTS = [
        // レガシーのレシート検証
        'verify_receipt' => [
            'production' => 'https://buy.itunes.apple.com/verifyReceipt',
            'sandbox' => 'https://sandbox.itunes.apple.com/verifyReceipt',
        ],
        // App Store Server API
        'server_api' => [
            'production' => 'https://api.storekit.itunes.apple.com',
            'sandbox' => 'https://api.storekit-sandbox.itunes.apple.com',
        ],
    ];

    /**
     * タイムアウト（秒）
     */
    private const TIMEOUT = 30;

    /**
     * レシート検証
     * 
     * @param array<string, mixed> $payload 検証リクエストのペイロード
     * @param bool|null $isSandbox Sandbox環境を使うか。nullなら設定に従う
     * @return array<string, mixed> レスポンス
     * @throws PlatformApiException API通信エラー時
     */
    public function verifyReceipt(array $payload, ?bool $isSandbox = null): array
    {
        $isSandbox ??= $this->environment() === 'sandbox';
        $url = self::ENDPOINTS['verify_receipt'][$isSandbox ? 'sandbox' : 'production'];

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->post($url, $payload);

            if (!$response->successful()) {
                throw new PlatformApiException(
                    "App Store API returned error: HTTP {$response->status()}"
                );
            }

            $data = $response->json();

            // ステータス21007（Sandbox receipt sent to Production）の場合は自動的にSandboxで再試行
            if (isset($data['status']) && $data['status'] === 21007 && !$isSandbox) {
                Log::info('App Store: Sandbox receipt detected, retrying with sandbox endpoint');
                return $this->verifyReceipt($payload, true);
            }

            return $data;

        } catch (\Exception $e) {
            Log::error('App Store API error', [
                'message' => $e->getMessage(),
                'url' => $url,
            ]);

            throw new PlatformApiException(
                "Failed to communicate with App Store: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    // ========================================
    // App Store Server API（JWT認証・JWS署名付きレスポンス）
    // ========================================

    /**
     * JWTのaudience（Appleが定めた固定値）
     */
    private const AUDIENCE = 'appstoreconnect-v1';

    /**
     * Appleが許容するJWTの最大有効期間（秒）
     */
    private const MAX_TTL = 3600;

        /**
     * サブスクリプションの全状態を取得
     *
     * GET /inApps/v1/subscriptions/{transactionId}
     *
     * @param  string  $transactionId  トランザクションID
     * @return array<string, mixed>
     *
     * @throws PlatformApiException
     */
    public function fetchSubscriptionStatuses(string $transactionId): array
    {
        return $this->serverApiGet("/inApps/v1/subscriptions/{$transactionId}", ['transaction_id' => $transactionId]);
    }

    /**
     * 返金履歴を取得
     *
     * GET /inApps/v2/refund/lookup/{transactionId}
     *
     * @param  string  $transactionId  トランザクションID（originalTransactionId）
     * @return array<string, mixed>
     *
     * @throws PlatformApiException
     */
    public function fetchRefundHistory(string $transactionId): array
    {
        return $this->serverApiGet("/inApps/v2/refund/lookup/{$transactionId}", ['transaction_id' => $transactionId]);
    }

    /**
     * JWS（署名付きペイロード）を検証して中身を返す
     *
     * ヘッダーのx5cにある証明書で署名を検証する。
     * jwt.root_certificate が設定されていれば、証明書チェーンも検証する。
     *
     * @param  string  $signedPayload  JWS文字列
     * @return array<string, mixed>
     *
     * @throws PlatformApiException 署名が不正な場合
     */
    public function decodeSignedPayload(string $signedPayload): array
    {
        $segments = explode('.', $signedPayload);

        if (count($segments) !== 3) {
            throw new PlatformApiException('App Store signed payload is malformed');
        }

        $header = json_decode($this->base64UrlDecode($segments[0]), true);

        if (! is_array($header) || ! isset($header['x5c']) || ! is_array($header['x5c']) || $header['x5c'] === []) {
            throw new PlatformApiException('App Store signed payload has no certificate chain');
        }

        /** @var list<string> $chain */
        $chain = array_values($header['x5c']);
        $leafCertificate = $this->toPemCertificate($chain[0]);

        $this->verifyCertificateChain($chain);

        try {
            $payload = JWT::decode($signedPayload, new Key($this->publicKeyOf($leafCertificate), 'ES256'));
        } catch (\Throwable $e) {
            throw new PlatformApiException(
                "Failed to verify App Store signed payload: {$e->getMessage()}",
                0,
                $e
            );
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(json_encode($payload) ?: '{}', true);

        return $decoded;
    }

    /**
     * GETリクエストを送る
     *
     * @param  array<string, string>  $logContext  失敗時にログへ出す情報
     * @return array<string, mixed>
     *
     * @throws PlatformApiException
     */
    private function serverApiGet(string $path, array $logContext): array
    {
        $url = $this->serverApiBaseUrl().$path;

        try {
            $response = Http::withToken($this->serverApiToken())
                ->timeout(self::TIMEOUT)
                ->get($url);

            if (! $response->successful()) {
                throw new PlatformApiException(
                    "App Store Server API returned error: HTTP {$response->status()}"
                );
            }

            /** @var array<string, mixed> $data */
            $data = $response->json() ?? [];

            return $data;
        } catch (PlatformApiException $e) {
            Log::error('App Store Server API error', $logContext + ['message' => $e->getMessage()]);

            throw $e;
        } catch (\Throwable $e) {
            Log::error('App Store Server API error', $logContext + ['message' => $e->getMessage()]);

            throw new PlatformApiException(
                "Failed to communicate with App Store Server API: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * App Store Server API のベースURL
     */
    private function serverApiBaseUrl(): string
    {
        return self::ENDPOINTS['server_api'][$this->environment()];
    }

    /**
     * 接続先の環境を返す
     *
     * レガシーのレシート検証とServer APIで判定がずれないよう、ここに集約する。
     *
     * @return 'production'|'sandbox'
     */
    private function environment(): string
    {
        return config('services.app_store.environment') === 'production' ? 'production' : 'sandbox';
    }

    /**
     * 認証用のJWTを作る
     *
     * 有効期間より短い時間だけキャッシュする。
     *
     * @throws PlatformApiException
     */
    private function serverApiToken(): string
    {
        $config = $this->jwtConfig();
        $cacheKey = 'billing:app_store:jwt:'.sha1($config['key_id'].$config['issuer_id'].$config['bundle_id']);

        $token = Cache::get($cacheKey);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $issuedAt = time();
        $ttl = min($config['ttl'], self::MAX_TTL);

        $token = JWT::encode(
            [
                'iss' => $config['issuer_id'],
                'iat' => $issuedAt,
                'exp' => $issuedAt + $ttl,
                'aud' => self::AUDIENCE,
                'bid' => $config['bundle_id'],
            ],
            $config['private_key'],
            'ES256',
            $config['key_id']
        );

        // 期限切れを跨がないよう、有効期間より1分短くキャッシュする
        Cache::put($cacheKey, $token, max($ttl - 60, 60));

        return $token;
    }

    /**
     * JWTの設定を読む
     *
     * @return array{key_id: string, issuer_id: string, bundle_id: string, private_key: string, ttl: int}
     *
     * @throws PlatformApiException
     */
    private function jwtConfig(): array
    {
        $keyId = config('services.app_store.jwt.key_id');
        $issuerId = config('services.app_store.jwt.issuer_id');
        $bundleId = config('services.app_store.jwt.bundle_id');
        $privateKey = config('services.app_store.jwt.private_key');

        foreach ([
            'APP_STORE_JWT_KEY_ID' => $keyId,
            'APP_STORE_JWT_ISSUER_ID' => $issuerId,
            'APP_STORE_JWT_BUNDLE_ID' => $bundleId,
            'APP_STORE_JWT_PRIVATE_KEY' => $privateKey,
        ] as $envName => $value) {
            if (! is_string($value) || $value === '') {
                throw new PlatformApiException("App Store Server API is not configured ({$envName})");
            }
        }

        return [
            'key_id' => (string) $keyId,
            'issuer_id' => (string) $issuerId,
            'bundle_id' => (string) $bundleId,
            'private_key' => $this->readPrivateKey((string) $privateKey),
            'ttl' => (int) config('services.app_store.jwt.ttl', 1800),
        ];
    }

    /**
     * 秘密鍵を読む（.p8のパス、またはPEM文字列そのもの）
     *
     * @throws PlatformApiException
     */
    private function readPrivateKey(string $source): string
    {
        if (str_contains($source, '-----BEGIN')) {
            return $source;
        }

        $pem = @file_get_contents($source);

        if ($pem === false) {
            throw new PlatformApiException("App Store private key file is not readable: {$source}");
        }

        return $pem;
    }

    /**
     * 証明書チェーンを検証する
     *
     * Apple Root CA - G3 が設定されている場合のみ実施する。
     * 未設定なら署名（リーフ証明書）の検証だけになるため、警告を残す。
     *
     * @param  list<string>  $chain  x5c（base64のDER証明書）
     *
     * @throws PlatformApiException
     */
    private function verifyCertificateChain(array $chain): void
    {
        $root = config('services.app_store.jwt.root_certificate');

        if (! is_string($root) || $root === '') {
            Log::warning(
                'App Store signed payload: certificate chain is not verified '
                .'(set APP_STORE_JWT_ROOT_CERTIFICATE to enable)'
            );

            return;
        }

        $rootPem = str_contains($root, '-----BEGIN') ? $root : @file_get_contents($root);

        if ($rootPem === false) {
            throw new PlatformApiException("App Store root certificate is not readable: {$root}");
        }

        // x5c は [リーフ, 中間, ルート] の順。各リンクを上位の公開鍵で検証する
        $certificates = array_map(fn (string $der) => $this->toPemCertificate($der), $chain);
        $certificates[] = $rootPem;

        for ($i = 0; $i < count($certificates) - 1; $i++) {
            if (openssl_x509_verify($certificates[$i], $certificates[$i + 1]) !== 1) {
                throw new PlatformApiException('App Store certificate chain verification failed');
            }
        }
    }

    /**
     * base64のDER証明書をPEMに変換する
     */
    private function toPemCertificate(string $base64Der): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            .chunk_split($base64Der, 64, "\n")
            ."-----END CERTIFICATE-----\n";
    }

    /**
     * 証明書から公開鍵を取り出す
     *
     * @throws PlatformApiException
     */
    private function publicKeyOf(string $pemCertificate): string
    {
        $certificate = openssl_x509_read($pemCertificate);

        if ($certificate === false) {
            throw new PlatformApiException('App Store certificate could not be parsed');
        }

        $publicKey = openssl_pkey_get_public($certificate);

        if ($publicKey === false) {
            throw new PlatformApiException('App Store certificate has no usable public key');
        }

        $details = openssl_pkey_get_details($publicKey);

        if ($details === false || ! isset($details['key'])) {
            throw new PlatformApiException('App Store public key could not be exported');
        }

        return (string) $details['key'];
    }

    /**
     * base64url をデコードする
     */
    private function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/'), true);
    }
}
