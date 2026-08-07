<?php

namespace NexusBilling\Contracts;

use NexusBilling\DTOs\ReceiptDto;
use NexusBilling\DTOs\SubscriptionStatus;
use NexusBilling\DTOs\VerificationDto;
use NexusBilling\Exceptions\ReceiptVerificationException;

/**
 * Billing プラットフォームインターフェース
 * 
 * AppStore、GooglePlay等のプラットフォーム固有の実装はこのインターフェースを実装する
 */
interface BillingPlatformInterface
{
    /**
     * レシート検証
     * 
     * クライアントから送信されたレシートをプラットフォームAPIで検証する
     * 
     * @param ReceiptDto $receiptData クライアントから送られたレシート情報
     * @return VerificationDto 検証結果
     * @throws ReceiptVerificationException 検証失敗時
     */
    public function verifyReceipt(ReceiptDto $receiptData): VerificationDto;

    /**
     * サブスクリプション状態確認
     * 
     * サブスクリプション商品の現在の状態を取得する
     * 
     * @param string $subscriptionId サブスクリプションID（プラットフォーム固有）
     * @return SubscriptionStatus サブスクリプション状態
     * @throws ReceiptVerificationException API通信エラー等
     */
    public function getSubscriptionStatus(string $subscriptionId): SubscriptionStatus;

    /**
     * 返金確認
     * 
     * 指定されたトランザクションが返金されているか確認する
     * 
     * @param string $transactionId トランザクションID
     * @return bool 返金されているか
     * @throws ReceiptVerificationException API通信エラー等
     */
    public function isRefunded(string $transactionId): bool;
}
