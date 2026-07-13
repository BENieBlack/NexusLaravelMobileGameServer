<?php

namespace LaravelMobileBilling\Contracts;

use LaravelMobileBilling\Exceptions\DuplicatePurchaseException;

/**
 * 冪等性チェックインターフェース
 * 
 * 購入処理の重複を防止するための冪等性チェック機能を提供
 */
interface IdempotencyCheckerInterface
{
    /**
     * トランザクションが既に処理済みかチェック
     * 
     * @param string $transactionId トランザクションID
     * @param int $playerId プレイヤーID
     * @return bool 処理済みの場合true
     */
    public function isProcessed(string $transactionId, int $playerId): bool;

    /**
     * トランザクションを処理済みとしてマーク
     * 
     * @param string $transactionId トランザクションID
     * @param int $playerId プレイヤーID
     * @param array $metadata 追加メタデータ（オプション）
     * @return void
     */
    public function markAsProcessed(string $transactionId, int $playerId, array $metadata = []): void;

    /**
     * トランザクションが未処理であることを確認し、処理済みとしてマーク
     * 
     * @param string $transactionId トランザクションID
     * @param int $playerId プレイヤーID
     * @param array $metadata 追加メタデータ（オプション）
     * @return void
     * @throws DuplicatePurchaseException 既に処理済みの場合
     */
    public function ensureNotProcessed(string $transactionId, int $playerId, array $metadata = []): void;
}
