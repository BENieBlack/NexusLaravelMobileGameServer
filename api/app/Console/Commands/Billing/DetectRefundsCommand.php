<?php

namespace App\Console\Commands\Billing;

use App\Models\Log\LogInAppPurchase;
use App\Repositories\Log\LogInAppPurchaseRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nexus\Core\Utilities\ClockUtility;
use NexusBilling\Facades\BillingFacade;
use Throwable;

/**
 * DetectRefundsCommand
 *
 * 購入済みの課金について、プラットフォーム側で返金されていないかを確認するバッチ。
 *
 * 返金を検知したら log_in_app_purchase に Refunded の行を追加する。
 * 付与済みリソースの回収は行わない（プレイヤーデータの変更を伴い、
 * 消費済みの場合の扱いなどゲーム運営の判断が要るため別途対応する）。
 *
 * ログDBはシャードされているため、全シャードを走査する。
 */
class DetectRefundsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'billing:detect-refunds
                            {--days=30 : 何日前までの購入を確認するか}
                            {--limit=500 : 1シャードあたりの確認件数の上限}
                            {--billing-platform= : 特定の決済プラットフォームのみ確認（AppStore, GooglePlay）}
                            {--dry-run : 検知のみ行い、ログには記録しない}';

    /**
     * @var string
     */
    protected $description = '購入済みの課金が返金されていないかをプラットフォームに問い合わせ、返金分をログに記録する';

    public function __construct(
        private readonly BillingFacade $billingFacade,
        private readonly LogInAppPurchaseRepository $logInAppPurchaseRepository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $limit = (int) $this->option('limit');
        $billingPlatform = $this->option('billing-platform');
        $isDryRun = (bool) $this->option('dry-run');

        $since = ClockUtility::now()->subDays($days)->format('Y-m-d H:i:s');

        $checked = 0;
        $refunded = 0;
        $failed = 0;

        foreach ($this->logConnections() as $connection) {
            foreach ($this->findPurchases($connection, $since, $limit, $billingPlatform) as $purchase) {
                $checked++;

                try {
                    if (! $this->billingFacade->isRefunded($purchase->billing_platform, $purchase->receipt_id)) {
                        continue;
                    }
                } catch (Throwable $e) {
                    $failed++;
                    $this->warn("確認に失敗: {$purchase->receipt_id} ({$purchase->billing_platform}) {$e->getMessage()}");

                    continue;
                }

                $refunded++;
                $this->line("返金を検知: player={$purchase->sys_player_id} transaction={$purchase->receipt_id} {$purchase->pay_string}");

                if ($isDryRun) {
                    continue;
                }

                $this->recordRefund($connection, $purchase);
            }
        }

        $this->newLine();
        $this->table(
            ['確認', '返金検知', '確認失敗'],
            [[$checked, $refunded, $failed]]
        );

        if ($isDryRun) {
            $this->comment('--dry-run のため、ログには記録していない');
        } elseif ($refunded > 0) {
            $this->comment('付与済みリソースの回収は行っていない。運営で対応すること');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * 走査対象のログDB接続を返す
     *
     * @return list<string>
     */
    private function logConnections(): array
    {
        $shardCount = (int) config('database.pitr.shard_count', 2);
        $connections = [];

        for ($i = 1; $i <= $shardCount; $i++) {
            $connections[] = "log{$i}";
        }

        return $connections;
    }

    /**
     * 返金確認の対象となる購入ログを取得する
     *
     * すでにRefundedを記録済みのトランザクションは除く。
     *
     * @return Collection<int, \stdClass>
     */
    private function findPurchases(
        string $connection,
        string $since,
        int $limit,
        ?string $billingPlatform
    ): Collection {
        $refundedReceiptIds = DB::connection($connection)
            ->table('log_in_app_purchase')
            ->where('status', LogInAppPurchase::STATUS_REFUNDED)
            ->pluck('receipt_id');

        return DB::connection($connection)
            ->table('log_in_app_purchase')
            ->where('status', LogInAppPurchase::STATUS_PURCHASED)
            ->where('system_at', '>=', $since)
            ->when($billingPlatform !== null, fn ($query) => $query->where('billing_platform', $billingPlatform))
            ->whereNotIn('receipt_id', $refundedReceiptIds)
            ->orderByDesc('system_at')
            ->limit($limit)
            ->get();
    }

    /**
     * 返金をログに記録する
     */
    private function recordRefund(string $connection, \stdClass $purchase): void
    {
        $this->logInAppPurchaseRepository->insertRefundedLog(
            uniqueRequestId: $purchase->unique_request_id.':refunded',
            sysPlayerId: (int) $purchase->sys_player_id,
            platform: $purchase->platform,
            billingPlatform: $purchase->billing_platform,
            receiptId: $purchase->receipt_id,
            receipt: [
                'detected_by' => 'billing:detect-refunds',
                'purchased_at' => $purchase->system_at,
            ],
            mstInAppPurchaseId: $purchase->mst_in_app_purchase_id,
            currencyCode: $purchase->currency_code,
            payAmount: (float) $purchase->pay_amount,
            payString: $purchase->pay_string,
            connection: $connection,
        );
    }
}
