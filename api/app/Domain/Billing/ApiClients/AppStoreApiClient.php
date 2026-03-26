<?php

namespace App\Domain\Billing\ApiClients;

use App\Domain\Billing\Exceptions\PlatformApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Apple App Store API クライアント
 * 
 * App Store のレシート検証APIとの通信を担当
 */
class AppStoreApiClient
{
    /**
     * Production環境のエンドポイント
     */
    private const PRODUCTION_URL = 'https://buy.itunes.apple.com/verifyReceipt';

    /**
     * Sandbox環境のエンドポイント
     */
    private const SANDBOX_URL = 'https://sandbox.itunes.apple.com/verifyReceipt';

    /**
     * タイムアウト（秒）
     */
    private const TIMEOUT = 30;

    /**
     * レシート検証
     * 
     * @param array $payload 検証リクエストのペイロード
     * @param bool $isSandbox Sandbox環境を使用するか
     * @return array レスポンス
     * @throws PlatformApiException API通信エラー時
     */
    public function verifyReceipt(array $payload, bool $isSandbox = false): array
    {
        $url = $isSandbox ? self::SANDBOX_URL : self::PRODUCTION_URL;

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

    /**
     * サブスクリプション状態取得
     * 
     * 注: App Store Server APIを使用する場合の実装
     * 実際の実装では JWT 認証などが必要
     * 
     * @param string $transactionId
     * @return array
     * @throws PlatformApiException
     */
    public function getSubscriptionStatus(string $transactionId): array
    {
        // TODO: App Store Server API の実装
        // https://developer.apple.com/documentation/appstoreserverapi
        
        throw new PlatformApiException('Subscription status API not implemented yet');
    }
}
