<?php

namespace App\Repositories\Trx;

use App\Models\Trx\TrxWalletBalance;
use LaravelWallet\Repositories\WalletBalanceRepositoryInterface;
use Nexus\Core\Utilities\ClockUtility;

/**
 * WalletBalanceRepositoryAdapter
 *
 * nexus-walletパッケージのWalletBalanceRepositoryInterfaceを実装
 * Application層のTrxWalletBalanceRepositoryをラップ
 */
class WalletBalanceRepositoryAdapter implements WalletBalanceRepositoryInterface
{
    public function __construct(
        private readonly TrxWalletBalanceRepository $trxWalletBalanceRepository,
    ) {}

    /**
     * FIFO順で残高レコードを取得
     *
     * 優先順位: is_paid DESC (有償優先) → expire_at ASC (有効期限が近いものから) → id ASC
     *
     * @param  int  $playerId  プレイヤーID
     * @param  string  $currencyId  通貨アイテムID
     * @return iterable<object> { id: int, is_paid: bool, current_amount: int, initial_amount: int, expire_at: ?string }
     */
    public function selectAllByCurrencyIdFifoOrder(int $playerId, string $currencyId): iterable
    {
        $balances = $this->trxWalletBalanceRepository->selectAllBalancesByMstItemId($currencyId);

        foreach ($balances as $balance) {
            yield (object) [
                'id' => $balance->getId(),
                'is_paid' => $balance->getIsPaid(),
                'current_amount' => $balance->getCurrentAmount(),
                'initial_amount' => $balance->getInitialAmount(),
                'expire_at' => $balance->getExpireAt()?->format('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * 有効期限切れの残高レコードを取得
     *
     * @param  int  $playerId  プレイヤーID
     * @param  string  $currencyId  通貨アイテムID
     * @param  string  $currentTime  現在時刻 (Y-m-d H:i:s)
     * @return iterable<object> { id: int, is_paid: bool, current_amount: int }
     */
    public function selectAllExpiredByCurrencyId(int $playerId, string $currencyId, string $currentTime): iterable
    {
        // パッケージ側は時刻を文字列で扱うため、Application層のCarbonImmutableに変換する
        $balances = $this->trxWalletBalanceRepository->selectAllExpiredBalancesByMstItemId(
            $currencyId,
            ClockUtility::parse($currentTime)
        );

        foreach ($balances as $balance) {
            yield (object) [
                'id' => $balance->getId(),
                'is_paid' => $balance->getIsPaid(),
                'current_amount' => $balance->getCurrentAmount(),
            ];
        }
    }

    /**
     * 残高レコードを作成
     *
     * @param  int  $playerId  プレイヤーID
     * @param  string  $currencyId  通貨アイテムID
     * @param  int  $amount  数量
     * @param  bool  $isPaid  有償フラグ
     * @param  string|null  $expireAt  有効期限 (Y-m-d H:i:s)、NULLの場合は無期限
     */
    public function insert(int $playerId, string $currencyId, int $amount, bool $isPaid, ?string $expireAt): void
    {
        $balance = new TrxWalletBalance([
            'sys_player_id' => $playerId,
            'mst_item_id' => $currencyId,
            'current_amount' => $amount,
            'initial_amount' => $amount,
            'is_paid' => $isPaid,
            'expire_at' => $expireAt,
        ]);
        $balance->exists = false; // INSERT として認識
        $this->trxWalletBalanceRepository->setModel($balance);
    }

    /**
     * 残高レコードの現在数量を更新
     *
     * @param  int  $balanceId  残高レコードID
     * @param  int  $newAmount  新しい数量
     */
    public function updateAmount(int $balanceId, int $newAmount): void
    {
        $balance = $this->trxWalletBalanceRepository->selectById($balanceId);

        if ($balance !== null) {
            $balance->setCurrentAmount($newAmount);
            $this->trxWalletBalanceRepository->setModel($balance);
        }
    }

    /**
     * 残高レコードを論理削除
     *
     * @param  int  $balanceId  残高レコードID
     */
    public function delete(int $balanceId): void
    {
        $balance = $this->trxWalletBalanceRepository->selectById($balanceId);

        if ($balance !== null) {
            $balance->setCurrentAmount(0);
            $this->trxWalletBalanceRepository->setModel($balance);
        }
    }
}
