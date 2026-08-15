<?php

namespace NexusBilling\Contracts;

use NexusBilling\DataTransferObjects\Receipt;
use NexusBilling\ValueObjects\Subscription;
use NexusBilling\DataTransferObjects\Verification;
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
     * @param Receipt $receipt クライアントから送られたレシート情報
     * @return Verification 検証結果
     * @throws ReceiptVerificationException 検証失敗時
     */
    public function verifyReceipt(Receipt $receipt): Verification;

    /**
     * サブスクリプション状態確認
     * 
     * サブスクリプション商品の現在の状態を取得する
     * 
     * @param string $subscriptionId サブスクリプションID（プラットフォーム固有）
     * @return Subscription サブスクリプション状態
     * @throws ReceiptVerificationException API通信エラー等
     */
    public function fetchSubscriptionStatus(string $subscriptionId): Subscription;

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
