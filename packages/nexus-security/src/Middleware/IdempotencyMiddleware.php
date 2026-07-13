<?php

namespace NexusSecurity\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * IdempotencyMiddleware
 * 
 * X-Unique-Request-Identifierヘッダーを使用してリクエストの冪等性を保証する
 * 同じリクエストが重複して送信された場合、キャッシュされたレスポンスを返す
 * 
 * キャッシュストア: Redis
 * キャッシュキー: "idempotency:{player_id}:{unique_request_id}:{path}"
 * キャッシュ期間: 設定可能（デフォルト: 24時間）
 * 圧縮: gzip圧縮を使用してメモリ使用量を削減
 */
class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 冪等性機能が無効の場合はスキップ
        if (!config('security.idempotency.enabled', true)) {
            return $next($request);
        }

        // X-Unique-Request-Identifierヘッダーを取得
        $uniqueRequestId = $request->headers->get('X-Unique-Request-Identifier');

        // ヘッダーが存在しない、またはGETリクエストの場合は冪等性チェックをスキップ
        if (!$uniqueRequestId || $request->isMethod('GET')) {
            return $next($request);
        }

        // プレイヤーIDを取得（認証済みの場合）
        $playerId = $request->input('authenticated_player_id');

        // 認証されていない場合（sign_up/sign_inなど）は冪等性チェックをスキップ
        if (!$playerId) {
            return $next($request);
        }

        // キャッシュキーを生成
        $cacheKey = $this->buildCacheKey($playerId, $uniqueRequestId, $request->path());

        // キャッシュが存在する場合、キャッシュされたレスポンスを返す
        if (Cache::has($cacheKey)) {
            $compressed = Cache::get($cacheKey);
            
            // gzip解凍してレスポンスデータを復元
            $jsonData = gzdecode($compressed);
            $cachedResponse = json_decode($jsonData, true);
            
            // キャッシュされたレスポンスを復元
            return response()->json(
                $cachedResponse['data'],
                $cachedResponse['status']
            )->withHeaders($cachedResponse['headers'] ?? [])
             ->header('X-Idempotency-Cache', 'HIT'); // キャッシュヒットを示すヘッダー
        }

        // リクエストを処理
        $response = $next($request);

        // 成功レスポンス（2xx）の場合のみキャッシュする
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->cacheResponse($cacheKey, $response);
        }

        // キャッシュミスを示すヘッダーを追加
        $response->header('X-Idempotency-Cache', 'MISS');

        return $response;
    }

    /**
     * キャッシュキーを生成
     *
     * @param int $playerId
     * @param string $uniqueRequestId
     * @param string $path
     * @return string
     */
    private function buildCacheKey(int $playerId, string $uniqueRequestId, string $path): string
    {
        // パスのスラッシュをコロンに置換してキーに使用
        $sanitizedPath = str_replace('/', ':', $path);
        
        return sprintf(
            '%s:%d:%s:%s',
            config('security.idempotency.cache_prefix', 'idempotency'),
            $playerId,
            $uniqueRequestId,
            $sanitizedPath
        );
    }

    /**
     * レスポンスをキャッシュする（gzip圧縮）
     *
     * @param string $cacheKey
     * @param Response $response
     * @return void
     */
    private function cacheResponse(string $cacheKey, Response $response): void
    {
        // レスポンスヘッダーを取得
        $headerBag = $response->headers;
        $contentType = $headerBag->get('Content-Type');
        
        $cachedData = [
            'data' => json_decode($response->getContent(), true),
            'status' => $response->getStatusCode(),
            'headers' => [
                'Content-Type' => $contentType,
            ],
        ];

        // JSONエンコード
        $jsonData = json_encode($cachedData);
        
        // gzip圧縮
        $compressionLevel = config('security.idempotency.compression_level', 6);
        $compressed = gzencode($jsonData, $compressionLevel);

        // 設定されたTTLを使用（デフォルト: 86400秒 = 24時間）
        $ttl = config('security.idempotency.cache_ttl', 86400);
        
        // 圧縮データをキャッシュに保存
        Cache::put($cacheKey, $compressed, $ttl);
    }
}
