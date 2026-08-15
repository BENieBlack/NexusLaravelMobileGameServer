<?php

namespace NexusBilling\Services;

use NexusBilling\DataTransferObjects\Verification;
use Illuminate\Support\Facades\Cache;

/**
 * 冪等性管理サービス
 * 
 * 同じリクエストIDでの重複購入を防止するためのサービス
 */
class IdempotencyService
{
    /**
     * キャッシュの有効期限（秒）
     * 24時間保持
     */
    private const CACHE_TTL = 86400;

    /**
     * キャッシュキーのプレフィックス
     */
    private const CACHE_KEY_PREFIX = 'billing:idempotency:';

    /**
     * 重複チェック
     * 
     * 指定されたリクエストIDが既に処理済みか確認する
     * 
     * @param string $uniqueRequestId 一意なリクエストID
     * @return bool 重複している場合true
     */
    public function isDuplicate(string $uniqueRequestId): bool
    {
        return Cache::has($this->buildCacheKey($uniqueRequestId));
    }

    /**
     * 処理済みとして登録
     * 
     * リクエストIDと検証結果をキャッシュに保存する
     * 
     * @param string $uniqueRequestId 一意なリクエストID
     * @param Verification $result 検証結果
     * @return void
     */
    public function register(string $uniqueRequestId, Verification $result): void
    {
        Cache::put(
            $this->buildCacheKey($uniqueRequestId),
            $result->toArray(),
            self::CACHE_TTL
        );
    }

    /**
     * 処理済み結果を取得
     * 
     * 既に処理済みのリクエストの結果を取得する
     * 
     * @param string $uniqueRequestId 一意なリクエストID
     * @return array|null 検証結果（存在しない場合null）
     */
    public function findResult(string $uniqueRequestId): ?array
    {
        return Cache::get($this->buildCacheKey($uniqueRequestId));
    }

    /**
     * キャッシュキーを構築
     * 
     * @param string $uniqueRequestId
     * @return string
     */
    private function buildCacheKey(string $uniqueRequestId): string
    {
        return self::CACHE_KEY_PREFIX . $uniqueRequestId;
    }

    /**
     * キャッシュをクリア（テスト用）
     * 
     * @param string $uniqueRequestId
     * @return void
     */
    public function forget(string $uniqueRequestId): void
    {
        Cache::forget($this->buildCacheKey($uniqueRequestId));
    }
}
