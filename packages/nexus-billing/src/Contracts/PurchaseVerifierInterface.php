<?php

namespace LaravelMobileBilling\Contracts;

use LaravelMobileBilling\DTOs\ReceiptData;
use LaravelMobileBilling\DTOs\VerificationResult;
use LaravelMobileBilling\Exceptions\ReceiptVerificationException;

/**
 * Purchase検証インターフェース
 * 
 * プラットフォームに依存しない購入検証の統一インターフェース
 */
interface PurchaseVerifierInterface
{
    /**
     * レシートを検証
     * 
     * プラットフォームに応じた適切な検証方法を選択し、レシートを検証する
     * 
     * @param ReceiptDataDto $receiptData レシート情報
     * @return VerificationResult 検証結果
     * @throws ReceiptVerificationException 検証失敗時
     */
    public function verify(ReceiptData $receiptData): VerificationResult;

    /**
     * プラットフォームをサポートしているか確認
     * 
     * @param string $platform プラットフォーム名
     * @return bool サポートしている場合true
     */
    public function supportsPlatform(string $platform): bool;
}
