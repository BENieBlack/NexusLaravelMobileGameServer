<?php

namespace App\Domain\InAppPurchase\Services;

use App\Domain\InAppPurchase\Contracts\DiamondBalanceInterface;
use App\Exceptions\TransactionDataException;
use App\Models\Trx\TrxDiamondBalance;
use App\Persistence\ApiSession;
use App\Repositories\Trx\TrxDiamondBalanceRepository;
use Nexus\Core\Resource\Constants\FreeDiamondConst;
use Nexus\Core\Resource\Services\DiamondService as PackageDiamondService;
use Nexus\Core\Resource\Services\ItemService as PackageItemService;

/**
 * InAppPurchaseDiamondBalanceService
 *
 * ダイヤの付与・消費の入口。無償 → 有償の順を保つのはここ（設計 free-diamond-item sequence.md 2・3 節）。
 *
 * - 無償ダイヤ: trx_item の free_diamond（platform 共通）。パッケージ版 ItemService を直接呼ぶ。
 *   Domain の ItemService（ItemInventoryInterface）は free_diamond を INVALID_ITEM_TYPE で拒否するので、
 *   ガードに当たらないよう、正規の消費はパッケージ版を使う（InitialGrantService の付与と同じ形）
 * - 有償ダイヤ: trx_diamond.paid_amount（X-Platform の行だけ）と、trx_diamond_balance の
 *   current_amount（購入単位。古い順 FIFO）を、同じトランザクションで一緒に減らす
 *
 * platform は ApiSession::getPlatform()（X-Platform）から読む。未設定なら RuntimeException
 * （暗黙の既定値を使わせない。HTTP 以外の経路からの消費は想定しない）。
 *
 * 書き込みは Repository の setModel()（UnitOfWork のキュー）だけで、DB への反映はコミット時。
 * 途中で例外が出ればキューごと捨てられ、無償・有償・balance のどれも書かれない。
 */
class InAppPurchaseDiamondBalanceService implements DiamondBalanceInterface
{
    public function __construct(
        private readonly PackageDiamondService $packageDiamondService,
        private readonly PackageItemService $packageItemService,
        private readonly TrxDiamondBalanceRepository $trxDiamondBalanceRepository,
    ) {}

    /**
     * {@inheritDoc}
     *
     * 運営調整などの経路用。balance の行は作らない（購入単位を持たない）
     */
    public function addPaidDiamond(int $sysPlayerId, string $platform, int $amount): void
    {
        $this->packageDiamondService->addPaidDiamond($sysPlayerId, $platform, $amount);
    }

    /**
     * 有償ダイヤモンドを加算し、FIFO用のバランスレコードを作成
     *
     * 購入（/in_app_purchase/buy）の経路。購入単位の行を作るので、paid_amount と
     * balance の合計が一致したまま増える（要件 Q23）。
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
        // 有償ダイヤモンドを加算（パッケージ層に委譲）
        $this->packageDiamondService->addPaidDiamond($sysPlayerId, $platform, $amount);

        // FIFO用のバランスレコードを作成（購入単位。current_amount = purchase_amount = 購入量）
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
     * {@inheritDoc}
     *
     * 無償は trx_item の free_diamond（行が無ければ 0。isPaidOnly なら読まない）、
     * 有償は X-Platform の trx_diamond の行（行が無ければ 0）。他 platform の行は読まない。
     */
    public function findDiamondAmount(int $sysPlayerId, bool $isPaidOnly = false): int
    {
        $free = $isPaidOnly ? 0 : $this->findFreeAmount($sysPlayerId);
        $paid = $this->packageDiamondService->findPaidAmount($sysPlayerId, ApiSession::getPlatform());

        return $free + $paid;
    }

    /**
     * ダイヤモンドを消費（無償 → 有償の順、または有償のみ）
     *
     * 流れ:
     * 1. 無償 F（isPaidOnly なら 0）と有償 P を読み、合計がコストに満たなければ何も書かず例外
     * 2. 無償から引く量 f = min(F, amount)。f > 0 のときだけ trx_item を減らす
     *    （0 になっても行は消さない。f = 0 なら trx_item を触らず、updated_resources にも載せない）
     * 3. 有償から引く量 p = amount - f。p > 0 のときだけ trx_diamond と balance を減らす
     *
     * @param  int  $sysPlayerId  プレイヤーID
     * @param  int  $amount  消費する数量
     * @param  bool  $isPaidOnly  有償ダイヤのみを消費するか（falseの場合は無償→有償の順）
     *
     * @throws \Exception 残高不足の場合
     */
    public function consumeDiamond(int $sysPlayerId, int $amount, bool $isPaidOnly = false): void
    {
        $platform = ApiSession::getPlatform();
        $free = $isPaidOnly ? 0 : $this->findFreeAmount($sysPlayerId);
        $paid = $this->packageDiamondService->findPaidAmount($sysPlayerId, $platform);

        // 呼び出し元が先に確かめているが、ここでも合計を再確認する（書き込みの前に落とす）
        if ($free + $paid < $amount) {
            throw new \Exception("ダイヤモンド残高が不足しています。必要: {$amount}, 現在: ".($free + $paid));
        }

        $freeToConsume = min($free, $amount);
        $paidToConsume = $amount - $freeToConsume;

        if ($freeToConsume > 0) {
            // パッケージ版 consumeItem は行が無いと例外にするので、f > 0（= 行がある）のときだけ呼ぶ。
            // free_diamond は paid_amount が常に 0 なので、有償優先の分岐は結果に影響しない
            $this->packageItemService->consumeItem($sysPlayerId, FreeDiamondConst::MST_ITEM_ID, $freeToConsume);
        }

        if ($paidToConsume > 0) {
            $this->packageDiamondService->consumePaidDiamond($sysPlayerId, $platform, $paidToConsume);
            $this->consumeBalanceFifo($sysPlayerId, $platform, $paidToConsume);
        }
    }

    /**
     * 無償ダイヤの残高（trx_item の free_diamond。行が無ければ 0）
     */
    private function findFreeAmount(int $sysPlayerId): int
    {
        return $this->packageItemService->findItemAmount($sysPlayerId, FreeDiamondConst::MST_ITEM_ID);
    }

    /**
     * 有償の消費量を、trx_diamond_balance の current_amount から古い順に引く（FIFO）
     *
     * trx_diamond.paid_amount を減らすのと同じトランザクションで呼ぶ。合計が足りなければ
     * （paid_amount と balance の不整合）、DIAMOND_NOT_ENOUGH にせず例外で投げ、
     * paid_amount の減算も含めて巻き戻す。
     *
     * @throws TransactionDataException balance の合計が引く量に満たないとき
     */
    private function consumeBalanceFifo(int $sysPlayerId, string $platform, int $amount): void
    {
        $remaining = $amount;

        foreach ($this->trxDiamondBalanceRepository->selectConsumableByPlatform($sysPlayerId, $platform) as $balance) {
            if ($remaining <= 0) {
                break;
            }

            // この行から引ける分。0 になっても行は消さない
            $take = min($balance->getCurrentAmount(), $remaining);
            $balance->setCurrentAmount($balance->getCurrentAmount() - $take);
            $this->trxDiamondBalanceRepository->setModel($balance);
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw TransactionDataException::diamondBalanceMismatch($sysPlayerId, $platform, $remaining);
        }
    }
}
