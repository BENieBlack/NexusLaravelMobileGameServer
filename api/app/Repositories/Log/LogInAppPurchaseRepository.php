<?php

namespace App\Repositories\Log;

use App\Models\Log\LogInAppPurchase;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;

/**
 * LogInAppPurchaseRepository
 *
 * アプリ内課金のログを管理するRepository
 * 課金関連のログなので isPurchaseLog = true
 *
 * @extends _BaseLogRepository<LogInAppPurchase>
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
     * @param  array<string, mixed>  $receipt
     */
    public function insertPurchaseLog(
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
            'system_at' => ClockUtility::now(),
        ]);

        // 注意: isPurchaseLog プロパティはここでは読まれていない。
        // setModel() に明示的に渡さない限り通常ログと同じ枠に登録され、
        // execAllLogs() で書かれる。
        // 課金ログを独立して書きたい場合は setModel($model, true) にしたうえで
        // 呼び出し側が execPurchaseQuery() を呼ぶ必要がある。
        // なお検証失敗時の記録は insertFailedPurchaseLog() が
        // トランザクション外へ直接INSERTしているため、そちらで担保されている。
        $this->setModel($model);
    }

    /**
     * 失敗した課金を即時にログへ記録する
     *
     * 検証失敗や付与失敗はトランザクションがロールバックされるため、
     * Unit of Work のキューに積むと消えてしまう。
     * CS調査で「購入を試みたが失敗した」を追えるように、
     * トランザクションの外へ直接INSERTする。
     *
     * unique_request_id が既にある場合（成功後の再送など）は何もしない。
     *
     * @param  array<string, mixed>  $receipt  レシートまたは検証レスポンス
     */
    public function insertFailedPurchaseLog(
        string $uniqueRequestId,
        int $sysPlayerId,
        string $platform,
        string $billingPlatform,
        string $receiptId,
        array $receipt,
        string $mstInAppPurchaseId,
        string $currencyCode,
        float $payAmount,
        string $payString
    ): void {
        $model = new LogInAppPurchase;

        DB::connection($model->getConnectionName())
            ->table($model->getTable())
            ->insertOrIgnore([
                'unique_request_id' => $uniqueRequestId,
                'sys_player_id' => $sysPlayerId,
                'platform' => $platform,
                'billing_platform' => $billingPlatform,
                'receipt_id' => $receiptId,
                'receipt' => json_encode($receipt, JSON_UNESCAPED_UNICODE),
                'status' => LogInAppPurchase::STATUS_FAILED,
                'mst_in_app_purchase_id' => $mstInAppPurchaseId,
                'currency_code' => $currencyCode,
                'pay_amount' => $payAmount,
                'pay_string' => $payString,
                'system_at' => ClockUtility::nowToString(),
                'created_at' => ClockUtility::nowToString(),
                'updated_at' => ClockUtility::nowToString(),
            ]);
    }

    /**
     * 返金を検知したことを即時にログへ記録する
     *
     * 購入時のログ（Purchased）はそのまま残し、Refundedの行を別に足す。
     * 履歴として両方を追えるようにするため。
     *
     * バッチから呼ぶためUnit of Workのトランザクション外で書き込む。
     * すでに同じ返金を記録済みなら何もしない。
     *
     * @param  array<string, mixed>  $receipt  返金確認時の情報
     */
    public function insertRefundedLog(
        string $uniqueRequestId,
        int $sysPlayerId,
        string $platform,
        string $billingPlatform,
        string $receiptId,
        array $receipt,
        string $mstInAppPurchaseId,
        string $currencyCode,
        float $payAmount,
        string $payString,
        string $connection
    ): void {
        $model = new LogInAppPurchase;

        DB::connection($connection)
            ->table($model->getTable())
            ->insertOrIgnore([
                'unique_request_id' => $uniqueRequestId,
                'sys_player_id' => $sysPlayerId,
                'platform' => $platform,
                'billing_platform' => $billingPlatform,
                'receipt_id' => $receiptId,
                'receipt' => json_encode($receipt, JSON_UNESCAPED_UNICODE),
                'status' => LogInAppPurchase::STATUS_REFUNDED,
                'mst_in_app_purchase_id' => $mstInAppPurchaseId,
                'currency_code' => $currencyCode,
                'pay_amount' => $payAmount,
                'pay_string' => $payString,
                'system_at' => ClockUtility::nowToString(),
                'created_at' => ClockUtility::nowToString(),
                'updated_at' => ClockUtility::nowToString(),
            ]);
    }
}
