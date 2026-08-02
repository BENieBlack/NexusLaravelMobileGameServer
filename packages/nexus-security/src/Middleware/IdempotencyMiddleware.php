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
 * 事前予約型: Cache::add()で先にキーを確保し、処理中の同時リクエストを409で拒否
 * 
 * キャッシュストア: Redis
 * キャッシュキー: "idempotency:{player_id}:{unique_request_id}:{path}"
 * キャッシュ期間: 設定可能（デフォルト: 24時間）
 * 圧縮: gzip圧縮を使用してメモリ使用量を削減
 */
class IdempotencyMiddleware
{
    /**
     * 処理中を示す値
     */
    private const PROCESSING = '__PROCESSING__';

    /**
     * 処理中の状態を保持する最大時間（秒）
     * リクエストがこの時間内に完了しない場合は異常とみなす
     */
    private const PROCESSING_TIMEOUT = 300; // 5分
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

        // キャッシュが存在する場合
        if (Cache::has($cacheKey)) {
            $cached = Cache::get($cacheKey);
            
            // 処理中の場合は409 Conflictを返す
            if ($cached === self::PROCESSING) {
                $response = response()->json([
                    'error_code' => 40900,
                    'message' => 'Request is being processed. Please retry later.',
                ], 409);
                $response->headers->set('X-Idempotency-Cache', 'PROCESSING');
                return $response;
            }
            
            // gzip解凍してレスポンスデータを復元
            $jsonData = gzdecode($cached);
            $cachedResponse = json_decode($jsonData, true);
            
            // キャッシュされたレスポンスを復元
            $response = response()->json(
                $cachedResponse['data'],
                $cachedResponse['status']
            )->withHeaders($cachedResponse['headers'] ?? []);
            $response->headers->set('X-Idempotency-Cache', 'HIT');
            return $response;
        }

        // 事前予約: Cache::add()でアトミックに処理中状態を登録
        // 既にキーが存在する場合（=同時リクエスト）はfalseが返る
        $reserved = Cache::add($cacheKey, self::PROCESSING, self::PROCESSING_TIMEOUT);
        
        if (!$reserved) {
            // 予約に失敗（=他のリクエストが先に予約した）
            $response = response()->json([
                'error_code' => 40900,
                'message' => 'Request is being processed. Please retry later.',
            ], 409);
            $response->headers->set('X-Idempotency-Cache', 'CONFLICT');
            return $response;
        }

        try {
            // リクエストを処理
            $response = $next($request);

            // 成功レスポンス（2xx）の場合のみキャッシュする
            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                $this->cacheResponse($cacheKey, $response);
            } else {
                // 失敗した場合は処理中フラグを削除
                Cache::forget($cacheKey);
            }

            // キャッシュミスを示すヘッダーを追加
            $response->headers->set('X-Idempotency-Cache', 'MISS');

            return $response;
            
        } catch (\Throwable $e) {
            // 例外発生時は処理中フラグを削除
            Cache::forget($cacheKey);
            throw $e;
        }
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
