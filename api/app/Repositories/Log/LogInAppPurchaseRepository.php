<?php

namespace App\Repositories\Log;

use App\Models\Log\LogInAppPurchase;
use App\Utilities\Clock;

/**
 * LogInAppPurchaseRepository
 *
 * アプリ内課金のログを管理するRepository
 * 課金関連のログなので isPurchaseLog = true
 */
class LogInAppPurchaseRepository extends _BaseLogRepository
{
    protected string $modelClass = LogInAppPurchase::class;

    /**
     * 課金ログであることを明示
     */
    protected bool $isPurchaseLog = true;

    /**
     * 課金ログを記録（Unit of Work パターン使用）
     * 課金ログはトランザクション内で実行される
     *
     * @param string $uniqueRequestId
     * @param int $sysPlayerId
     * @param string $platform
     * @param string $billingPlatform
     * @param string $receiptId
     * @param array $receipt
     * @param string $status
     * @param string $mstInAppPurchaseId
     * @param string $currencyCode
     * @param float $payAmount
     * @param string $payString
     * @return void
     */
    public function createPurchaseLog(
        string $uniqueRequestId,
        int $sysPlayerId,
        string $platform,
        string $billingPlatform,
        string $receiptId,
        array $receipt,
        string $status,
        string $mstInAppPurchaseId,
        string $currencyCode,
        float $payAmount,
        string $payString
    ): void {
        $model = new LogInAppPurchase([
            'unique_request_id' => $uniqueRequestId,
            'sys_player_id' => $sysPlayerId,
            'platform' => $platform,
            'billing_platform' => $billingPlatform,
            'receipt_id' => $receiptId,
            'receipt' => $receipt,
            'status' => $status,
            'mst_in_app_purchase_id' => $mstInAppPurchaseId,
            'currency_code' => $currencyCode,
            'pay_amount' => $payAmount,
            'pay_string' => $payString,
            'system_at' => Clock::now(),
            'created_at' => Clock::now(),
        ]);

        // 課金ログとして登録（isPurchaseLogプロパティが使用される）
        $this->setModel($model);
    }
}
