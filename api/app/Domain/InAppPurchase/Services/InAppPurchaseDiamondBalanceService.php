<?php

namespace App\Domain\InAppPurchase\Services;

use App\Models\Trx\TrxDiamondBalance;
use App\Repositories\Trx\TrxDiamondBalanceRepository;
use NexusResource\Services\DiamondService as PackageDiamondService;

/**
 * InAppPurchaseDiamondBalanceService (Domain層ラッパー)
 *
 * パッケージ層のDiamondServiceをラップ
 *
 * Design Pattern: Wrapper Pattern
 * - Package層: DTOベースのビジネスロジック
 * - Domain層: パッケージ層への委譲
 *
 * Responsibilities:
 * - パッケージ層Serviceへの委譲
 * - 追加機能（FIFOバランスレコード作成）
 *
 * Note: コアのビジネスロジックはパッケージ層（NexusResource\Services\DiamondService）に存在
 */
class InAppPurchaseDiamondBalanceService
{
    public function __construct(
        private readonly PackageDiamondService $packageDiamondService,
        private readonly TrxDiamondBalanceRepository $trxDiamondBalanceRepository,
    ) {}

    /**
     * ダイヤモンド残高を取得
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @return array{paid_amount: int, free_amount: int, total_amount: int}
     */
    public function findBalance(int $sysPlayerId, string $platform): array
    {
        // パッケージ層に委譲
        return $this->packageDiamondService->findBalance($sysPlayerId, $platform);
    }

    /**
     * ダイヤモンドを加算（有償/無償）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  int  $amount  加算する数量
     * @param  bool  $isPaid  有償ダイヤモンドか（falseの場合は無償）
     */
    public function addDiamond(int $sysPlayerId, string $platform, int $amount, bool $isPaid = false): void
    {
        // パッケージ層に委譲
        $this->packageDiamondService->addDiamond($sysPlayerId, $platform, $amount, $isPaid);
    }

    /**
     * 有償ダイヤモンドを加算し、FIFO用のバランスレコードを作成
     *
     * Note: この機能はDomain層特有の追加機能（FIFO管理）のため、ここに残す
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  string  $billingPlatform  決済プラットフォーム（AppStore, GooglePlay等）
     * @param  int  $amount  加算する数量
     * @param  float  $unitPrice  単価（返金計算用）
     */
    public function addPaidDiamondWithBalance(
        int $sysPlayerId,
        string $platform,
        string $billingPlatform,
        int $amount,
        float $unitPrice
    ): void {
        // ダイヤモンド残高を加算（パッケージ層に委譲）
        $this->packageDiamondService->addDiamond($sysPlayerId, $platform, $amount, isPaid: true);

        // FIFO用のバランスレコードを作成（Domain層の追加機能）
        $balance = new TrxDiamondBalance([
            'sys_player_id' => $sysPlayerId,
            'platform' => $platform,
            'billing_platform' => $billingPlatform,
            'current_amount' => $amount,
            'purchase_amount' => $amount,
            'unit_price' => $unitPrice,
        ]);
        $this->trxDiamondBalanceRepository->setModel($balance);
    }

    /**
     * ダイヤモンドを消費（無償 → 有償の順で消費、または有償のみ）
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $amount  消費する数量
     * @param  bool  $isPaidOnly  有償ダイヤのみを消費するか（falseの場合は無償→有償の順）
     *
     * @throws \Exception 残高不足の場合
     */
    public function consumeDiamond(int $sysPlayerId, int $amount, bool $isPaidOnly = false): void
    {
        // パッケージ層に委譲
        $this->packageDiamondService->consumeDiamond($sysPlayerId, $amount, $isPaidOnly);
    }
}
