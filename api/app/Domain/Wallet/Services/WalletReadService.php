<?php

namespace App\Domain\Wallet\Services;

use App\Repositories\Trx\TrxWalletRepository;
use LaravelWallet\DTOs\CurrencyBalanceDto;

/**
 * WalletReadService
 * 
 * 通貨残高の読み取り専用操作を担当するサービス
 * 
 * 責任:
 * - 通貨残高の取得（単一/複数）
 * - 状態変更なし、読み取りのみ
 * 
 * 設計:
 * - Read側: このサービス（状態変更なし）
 * - Write側: WriteService（状態変更あり）
 */
class WalletReadService
{
    public function __construct(
        private readonly TrxWalletRepository $trxWalletRepository,
    ) {
    }

    /**
     * 通貨残高を取得
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨アイテムID
     * @return CurrencyBalanceDto 残高情報
     */
    public function getBalance(int $playerId, string $currencyId): CurrencyBalanceDto
    {
        // Repository経由で取得
        $wallet = $this->trxWalletRepository->selectByMstItemId($playerId, $currencyId);

        if ($wallet === null) {
            return new CurrencyBalanceDto(
                freeAmount: 0,
                paidAmount: 0,
                totalAmount: 0,
            );
        }

        return new CurrencyBalanceDto(
            freeAmount: $wallet->getFreeAmount(),
            paidAmount: $wallet->getPaidAmount(),
            totalAmount: $wallet->getTotalAmount(),
        );
    }

    /**
     * 複数通貨の残高を一括取得
     * 
     * @param int $playerId プレイヤーID
     * @param array<string> $currencyIds 通貨IDリスト
     * @return array<string, CurrencyBalanceDto> 通貨ID => 残高情報のマップ
     */
    public function getBulkBalances(int $playerId, array $currencyIds): array
    {
        $result = [];
        foreach ($currencyIds as $currencyId) {
            $result[$currencyId] = $this->getBalance($playerId, $currencyId);
        }
        return $result;
    }
}
