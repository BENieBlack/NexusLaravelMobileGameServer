<?php

namespace LaravelWallet\Contracts;

use Carbon\CarbonImmutable;
use LaravelWallet\DTOs\CurrencyBalance;
use LaravelWallet\DTOs\CurrencyOperationResult;
use LaravelWallet\Exceptions\InsufficientBalanceException;
use LaravelWallet\Exceptions\InvalidCurrencyException;

/**
 * Wallet管理インターフェース
 * 
 * 仮想通貨の増減・残高管理を行うための統一インターフェース
 * アプリケーション側でこのインターフェースを実装する
 */
interface WalletManagerInterface
{
    /**
     * 通貨を加算
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨ID（例: "gold", "event_coin"）
     * @param int $freeAmount 無償通貨数（デフォルト: 0）
     * @param int $paidAmount 有償通貨数（デフォルト: 0）
     * @param CarbonImmutable|null $expireAt 有効期限（NULLの場合は無期限）
     * @return CurrencyOperationResult 操作結果
     * @throws InvalidCurrencyException 無効な通貨IDの場合
     */
    public function addCurrency(
        int $playerId,
        string $currencyId,
        int $freeAmount = 0,
        int $paidAmount = 0,
        ?CarbonImmutable $expireAt = null
    ): CurrencyOperationResult;

    /**
     * 通貨を消費（FIFO方式、有償優先）
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨ID
     * @param int $amount 消費する数量
     * @return CurrencyOperationResult 操作結果
     * @throws InsufficientBalanceException 残高不足の場合
     * @throws InvalidCurrencyException 無効な通貨IDの場合
     */
    public function consumeCurrency(
        int $playerId,
        string $currencyId,
        int $amount
    ): CurrencyOperationResult;

    /**
     * 通貨残高を取得
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨ID
     * @return CurrencyBalance 残高情報
     * @throws InvalidCurrencyException 無効な通貨IDの場合
     */
    public function getBalance(int $playerId, string $currencyId): CurrencyBalance;

    /**
     * 有効期限切れの通貨を削除
     * 
     * @param int $playerId プレイヤーID
     * @param string $currencyId 通貨ID
     * @return int 削除された数量
     * @throws InvalidCurrencyException 無効な通貨IDの場合
     */
    public function removeExpiredCurrency(int $playerId, string $currencyId): int;

    /**
     * 複数通貨の残高を一括取得
     * 
     * @param int $playerId プレイヤーID
     * @param array<string> $currencyIds 通貨IDリスト
     * @return array<string, CurrencyBalance> 通貨ID => 残高情報のマップ
     */
    public function getBulkBalances(int $playerId, array $currencyIds): array;
}
