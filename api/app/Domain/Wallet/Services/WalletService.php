<?php

namespace App\Domain\Wallet\Services;

use LaravelWallet\Contracts\WalletManagerInterface;
use LaravelWallet\DTOs\CurrencyBalanceDto;
use LaravelWallet\DTOs\CurrencyOperationResultDto;
use App\Domain\Wallet\Services\ReadService as WalletReadService;
use App\Domain\Wallet\Services\WriteService as WalletWriteService;

/**
 * WalletService (Facade)
 * 
 * 通貨関連の操作を提供する Facade
 * 
 * 既存コードとの互換性のため、このクラスを残します。
 * 内部では Read/Write Serviceに処理を委譲します。
 * 
 * 新規コードでは、以下のServiceを直接使用することを推奨:
 * - ReadService: 残高取得（読み取り専用）
 * - WriteService: 通貨加算・消費（書き込み）
 * 
 * @deprecated 新規コードでは ReadService または WriteService を使用してください
 */
class WalletService implements WalletManagerInterface
{
    public function __construct(
        private readonly WalletReadService $walletReadService,
        private readonly WalletWriteService $walletWriteService,
    ) {
    }

    /**
     * 通貨を加算
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID（例: "gold", "event_coin"）
     * @param int $freeAmount 無償通貨数（デフォルト: 0）
     * @param int $paidAmount 有償通貨数（デフォルト: 0）
     * @param string|null $expireAt 有効期限 (Y-m-d H:i:s)（NULLの場合は無期限）
     * @return CurrencyOperationResultDto 操作結果
     * 
     * @deprecated WriteService::addCurrency() を使用してください
     */
    public function addCurrency(
        int $playerId,
        string $currencyId,
        int $freeAmount = 0,
        int $paidAmount = 0,
        ?string $expireAt = null
    ): CurrencyOperationResultDto {
        return $this->walletWriteService->addCurrency($playerId, $currencyId, $freeAmount, $paidAmount, $expireAt);
    }

    /**
     * 通貨を消費（FIFO方式、有償優先）
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @param int $amount 消費する数量
     * @return CurrencyOperationResultDto 操作結果
     * @throws \LaravelWallet\Exceptions\InsufficientBalanceException 残高不足の場合
     * 
     * @deprecated WriteService::consumeCurrency() を使用してください
     */
    public function consumeCurrency(
        int $playerId,
        string $currencyId,
        int $amount
    ): CurrencyOperationResultDto {
        return $this->walletWriteService->consumeCurrency($playerId, $currencyId, $amount);
    }

    /**
     * 通貨残高を取得
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @return CurrencyBalanceDto 残高情報
     * 
     * @deprecated ReadService::getBalance() を使用してください
     */
    public function getBalance(int $playerId, string $currencyId): CurrencyBalanceDto
    {
        return $this->walletReadService->getBalance($playerId, $currencyId);
    }

    /**
     * 有効期限切れの通貨を削除
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @return int 削除された数量
     * 
     * @deprecated WriteService::removeExpiredCurrency() を使用してください
     */
    public function removeExpiredCurrency(int $playerId, string $currencyId): int
    {
        return $this->walletWriteService->removeExpiredCurrency($playerId, $currencyId);
    }

    /**
     * 複数通貨の残高を一括取得
     * 
     * @param int $playerId プレイヤーID
     * @param array<string> $currencyIds 通貨IDリスト
     * @return array<string, CurrencyBalanceDto> 通貨ID => 残高情報のマップ
     * 
     * @deprecated ReadService::getBulkBalances() を使用してください
     */
    public function getBulkBalances(int $playerId, array $currencyIds): array
    {
        return $this->walletReadService->getBulkBalances($playerId, $currencyIds);
    }
}
