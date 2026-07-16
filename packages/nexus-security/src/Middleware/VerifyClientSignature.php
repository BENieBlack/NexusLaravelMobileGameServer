<?php

namespace NexusSecurity\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerifyClientSignature Middleware
 * 
 * クライアントからのリクエストが正規のアプリからのものであることを検証します。
 * 
 * 検証項目:
 * 1. HMAC署名の検証（リクエストボディ + タイムスタンプ + ノンス）
 * 2. タイムスタンプの検証（5分以内のリクエストのみ許可）
 * 3. ノンスの検証（同一ノンスの再利用を防止）
 * 
 * リクエストヘッダー:
 * - X-Client-Timestamp: Unix timestamp (秒)
 * - X-Client-Nonce: ランダムな文字列（32文字以上推奨）
 * - X-Client-Signature: HMAC-SHA256署名
 * 
 * 署名の計算方法:
 * signature = HMAC-SHA256(
 *   key: CLIENT_SECRET,
 *   message: "{timestamp}:{nonce}:{request_body}"
 * )
 */
class VerifyClientSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        \Log::info('VerifyClientSignature: Starting verification', [
            'url' => $request->url(),
            'method' => $request->method(),
        ]);

        // 開発環境で署名検証をスキップする設定
        if (config('app.env') === 'local' && config('security.client_signature.skip_in_local', false)) {
            \Log::info('Client signature verification skipped in local environment');
            return $next($request);
        }

        // 必須ヘッダーの取得
        $timestamp = $request->header('X-Client-Timestamp');
        $nonce = $request->header('X-Client-Nonce');
        $signature = $request->header('X-Client-Signature');

        \Log::info('VerifyClientSignature: Headers received', [
            'timestamp' => $timestamp,
            'nonce' => $nonce ? substr($nonce, 0, 8) . '...' : null,
            'signature' => $signature ? substr($signature, 0, 16) . '...' : null,
        ]);

        // ヘッダーが存在しない場合はエラー
        if (!$timestamp || !$nonce || !$signature) {
            \Log::warning('VerifyClientSignature: Missing headers');
            return response()->json([
                'error' => 'INVALID_CLIENT_REQUEST',
                'message' => 'Missing required client authentication headers',
            ], 401);
        }

        // 1. タイムスタンプの検証
        $currentTime = time();
        $timeDiff = abs($currentTime - (int)$timestamp);
        $timestampTolerance = config('security.client_signature.timestamp_tolerance', 300);

        \Log::info('VerifyClientSignature: Timestamp check', [
            'current' => $currentTime,
            'request' => $timestamp,
            'diff' => $timeDiff,
            'tolerance' => $timestampTolerance,
        ]);

        if ($timeDiff > $timestampTolerance) {
            \Log::warning('VerifyClientSignature: Timestamp expired');
            return response()->json([
                'error' => 'REQUEST_EXPIRED',
                'message' => 'Request timestamp is too old or too far in the future',
            ], 401);
        }

        // 2. ノンスの検証（重複チェック）
        $nonceCacheKey = "client_nonce:{$nonce}";
        
        \Log::info('VerifyClientSignature: Checking nonce cache');
        
        if (Cache::has($nonceCacheKey)) {
            \Log::warning('VerifyClientSignature: Duplicate nonce detected');
            return response()->json([
                'error' => 'DUPLICATE_REQUEST',
                'message' => 'Request nonce has already been used',
            ], 401);
        }

        // 3. 署名の検証
        $requestBody = $request->getContent();
        
        \Log::info('VerifyClientSignature: Generating signature', [
            'body_length' => strlen($requestBody),
        ]);
        
        try {
            $expectedSignature = $this->generateSignature($timestamp, $nonce, $requestBody);
            
            \Log::info('VerifyClientSignature: Signature generated', [
                'expected' => substr($expectedSignature, 0, 16) . '...',
                'received' => substr($signature, 0, 16) . '...',
            ]);
        } catch (\Exception $e) {
            \Log::error('VerifyClientSignature: Signature generation failed', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if (!hash_equals($expectedSignature, $signature)) {
            \Log::warning('VerifyClientSignature: Signature mismatch');
            return response()->json([
                'error' => 'INVALID_SIGNATURE',
                'message' => 'Client signature verification failed',
            ], 401);
        }

        \Log::info('VerifyClientSignature: Signature verified successfully');

        // ノンスをキャッシュに保存（再利用を防止）
        $nonceCacheTtl = config('security.client_signature.nonce_cache_ttl', 600);
        Cache::put($nonceCacheKey, true, $nonceCacheTtl);

        return $next($request);
    }

    /**
     * HMAC-SHA256署名を生成
     * 
     * @param string $timestamp Unix timestamp
     * @param string $nonce ランダム文字列
     * @param string $body リクエストボディ
     * @return string HMAC署名（16進数）
     */
    private function generateSignature(string $timestamp, string $nonce, string $body): string
    {
        $secret = config('security.client_signature.secret');
        
        \Log::info('VerifyClientSignature: generateSignature called', [
            'secret_exists' => !empty($secret),
            'secret_length' => $secret ? strlen($secret) : 0,
        ]);
        
        if (!$secret) {
            throw new \RuntimeException('CLIENT_SECRET is not configured');
        }

        $message = "{$timestamp}:{$nonce}:{$body}";
        
        return hash_hmac('sha256', $message, $secret);
    }
}
