<?php

namespace NexusBilling\ApiClients;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NexusBilling\Exceptions\PlatformApiException;

/**
 * Google Play Developer API クライアント
 *
 * Google Play の購入検証APIとの通信を担当
 *
 * 認証はサービスアカウントのJWT assertionでアクセストークンを取得する方式。
 * google/apiclient は依存が重いため使わず、firebase/php-jwt + HTTPで完結させる。
 *
 * 必要な設定（config/services.php の google_play）:
 * - package_name: アプリのパッケージ名
 * - service_account: サービスアカウントJSONのパス、またはJSON文字列
 */
class GooglePlayApiClient
{
    /**
     * Google Play Developer API のベースURL
     */
    private const API_BASE_URL = 'https://androidpublisher.googleapis.com/androidpublisher/v3';

    /**
     * アクセストークン発行エンドポイント
     */
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /**
     * 要求するスコープ
     */
    private const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';

    /**
     * タイムアウト（秒）
     */
    private const TIMEOUT = 30;

    /**
     * アクセストークンの有効期間（秒）
     */
    private const TOKEN_TTL = 3600;

    /**
     * キャッシュ時間（秒）。有効期限より短くして期限切れを跨がないようにする
     */
    private const TOKEN_CACHE_TTL = 3300;

    /**
     * 購入検証（Products.purchases API）
     *
     * @param  string  $packageName  アプリのパッケージ名
     * @param  string  $productId  商品ID
     * @param  string  $token  購入トークン
     * @return array<string, mixed> レスポンス
     *
     * @throws PlatformApiException
     */
    public function verifyPurchase(string $packageName, string $productId, string $token): array
    {
        return $this->get(
            sprintf(
                '%s/applications/%s/purchases/products/%s/tokens/%s',
                self::API_BASE_URL,
                rawurlencode($packageName),
                rawurlencode($productId),
                rawurlencode($token)
            ),
            ['package_name' => $packageName, 'product_id' => $productId]
        );
    }

    /**
     * サブスクリプション取得（Subscriptions.purchases API）
     *
     * @param  string  $packageName  アプリのパッケージ名
     * @param  string  $subscriptionId  サブスクリプションID
     * @param  string  $token  購入トークン
     * @return array<string, mixed>
     *
     * @throws PlatformApiException
     */
    public function fetchSubscription(string $packageName, string $subscriptionId, string $token): array
    {
        return $this->get(
            sprintf(
                '%s/applications/%s/purchases/subscriptions/%s/tokens/%s',
                self::API_BASE_URL,
                rawurlencode($packageName),
                rawurlencode($subscriptionId),
                rawurlencode($token)
            ),
            ['package_name' => $packageName, 'subscription_id' => $subscriptionId]
        );
    }

    /**
     * 注文取得（Orders API）
     *
     * 返金の判定に使う。購入トークンを持たない場合でも orderId から引ける。
     *
     * @param  string  $packageName  アプリのパッケージ名
     * @param  string  $orderId  注文ID（verifyPurchaseのorderId）
     * @return array<string, mixed>
     *
     * @throws PlatformApiException
     */
    public function fetchOrder(string $packageName, string $orderId): array
    {
        return $this->get(
            sprintf(
                '%s/applications/%s/orders/%s',
                self::API_BASE_URL,
                rawurlencode($packageName),
                rawurlencode($orderId)
            ),
            ['package_name' => $packageName, 'order_id' => $orderId]
        );
    }

    /**
     * GETリクエストを送る
     *
     * @param  array<string, string>  $logContext  失敗時にログへ出す情報
     * @return array<string, mixed>
     *
     * @throws PlatformApiException
     */
    private function get(string $url, array $logContext): array
    {
        try {
            $response = Http::withToken($this->accessToken())
                ->timeout(self::TIMEOUT)
                ->get($url);

            if (! $response->successful()) {
                throw new PlatformApiException(
                    "Google Play API returned error: HTTP {$response->status()}"
                );
            }

            /** @var array<string, mixed> $data */
            $data = $response->json() ?? [];

            return $data;
        } catch (PlatformApiException $e) {
            Log::error('Google Play API error', $logContext + ['message' => $e->getMessage()]);

            throw $e;
        } catch (\Throwable $e) {
            Log::error('Google Play API error', $logContext + ['message' => $e->getMessage()]);

            throw new PlatformApiException(
                "Failed to communicate with Google Play: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * アクセストークンを取得する
     *
     * サービスアカウントの秘密鍵で署名したJWTを渡してトークンを受け取る。
     * 有効期限より短い時間だけキャッシュする。
     *
     * @throws PlatformApiException
     */
    private function accessToken(): string
    {
        $credentials = $this->serviceAccount();
        $cacheKey = 'billing:google_play:access_token:'.sha1($credentials['client_email']);

        $token = Cache::get($cacheKey);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $issuedAt = time();
        $assertion = JWT::encode(
            [
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => self::TOKEN_URL,
                'iat' => $issuedAt,
                'exp' => $issuedAt + self::TOKEN_TTL,
            ],
            $credentials['private_key'],
            'RS256'
        );

        $response = Http::asForm()
            ->timeout(self::TIMEOUT)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            throw new PlatformApiException(
                "Failed to obtain Google Play access token: HTTP {$response->status()}"
            );
        }

        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new PlatformApiException('Google Play access token was not returned');
        }

        Cache::put($cacheKey, $accessToken, self::TOKEN_CACHE_TTL);

        return $accessToken;
    }

    /**
     * サービスアカウントの認証情報を読む
     *
     * config値はJSONそのものでも、JSONファイルのパスでもよい。
     *
     * @return array{client_email: string, private_key: string}
     *
     * @throws PlatformApiException
     */
    private function serviceAccount(): array
    {
        $source = config('services.google_play.service_account');

        if (! is_string($source) || $source === '') {
            throw new PlatformApiException(
                'Google Play service account is not configured (services.google_play.service_account)'
            );
        }

        $json = str_starts_with(ltrim($source), '{') ? $source : @file_get_contents($source);

        if ($json === false) {
            throw new PlatformApiException("Google Play service account file is not readable: {$source}");
        }

        $credentials = json_decode($json, true);

        if (! is_array($credentials)
            || ! is_string($credentials['client_email'] ?? null)
            || ! is_string($credentials['private_key'] ?? null)
        ) {
            throw new PlatformApiException(
                'Google Play service account JSON must contain client_email and private_key'
            );
        }

        return [
            'client_email' => $credentials['client_email'],
            'private_key' => $credentials['private_key'],
        ];
    }
}
