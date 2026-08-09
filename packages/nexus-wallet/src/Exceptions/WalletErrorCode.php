<?php

namespace LaravelWallet\Exceptions;

/**
 * WalletErrorCode
 *
 * nexus-walletパッケージのエラーコード定義（4桁: 1000-1099）
 *
 * パッケージ層エラーコードルール：
 * - 4桁のコードを使用（再利用可能性を考慮）
 * - nexus-walletの範囲: 1000-1099
 */
class WalletErrorCode
{
    /**
     * 残高不足エラー
     * 通貨を消費しようとした際に残高が不足している
     */
    public const INSUFFICIENT_BALANCE = 1001;

    /**
     * 無効な通貨ID
     * 指定された通貨IDが存在しないか無効
     */
    public const INVALID_CURRENCY = 1002;

    /**
     * ウォレット未検出
     * プレイヤーのウォレットデータが見つからない
     */
    public const WALLET_NOT_FOUND = 1003;

    /**
     * 無効な金額
     * 0以下の金額や上限を超える金額が指定された
     */
    public const INVALID_AMOUNT = 1004;

    /**
     * 通貨の最大値超過
     * 加算後の金額が通貨の最大保持可能量を超える
     */
    public const CURRENCY_OVERFLOW = 1005;

    /**
     * トランザクション競合
     * 並行処理による競合が発生
     */
    public const TRANSACTION_CONFLICT = 1006;
}
