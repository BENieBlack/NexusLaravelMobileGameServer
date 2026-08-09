<?php

namespace App\Domain\Item\Constants;

/**
 * WalletConst
 *
 * Wallet管理通貨の定数を定義
 */
class WalletConst
{
    /**
     * Wallet管理対象の通貨アイテムID
     *
     * これらの通貨は trx_wallet / trx_wallet_balance で管理される
     */
    public const CURRENCY_GOLD = 'gold';

    public const CURRENCY_EVENT_COIN = 'event_coin';

    public const CURRENCY_RAID_MEDAL = 'raid_medal';

    public const CURRENCY_PVP_POINT = 'pvp_point';

    public const CURRENCY_GVG_POINT = 'gvg_point';

    /**
     * すべてのWallet管理通貨のリスト
     *
     * @var array<string>
     */
    public const WALLET_CURRENCIES = [
        self::CURRENCY_GOLD,
        self::CURRENCY_EVENT_COIN,
        self::CURRENCY_RAID_MEDAL,
        self::CURRENCY_PVP_POINT,
        self::CURRENCY_GVG_POINT,
    ];

    /**
     * 通貨の表示名マップ
     *
     * @var array<string, string>
     */
    public const CURRENCY_NAMES = [
        self::CURRENCY_GOLD => 'Gold',
        self::CURRENCY_EVENT_COIN => 'Event Coin',
        self::CURRENCY_RAID_MEDAL => 'Raid Medal',
        self::CURRENCY_PVP_POINT => 'PvP Point',
        self::CURRENCY_GVG_POINT => 'GvG Point',
    ];

    /**
     * 指定されたアイテムIDがWallet管理通貨かどうかをチェック
     */
    public static function isWalletCurrency(string $mstItemId): bool
    {
        return in_array($mstItemId, self::WALLET_CURRENCIES, true);
    }

    /**
     * 通貨の表示名を取得
     */
    public static function getCurrencyName(string $mstItemId): ?string
    {
        return self::CURRENCY_NAMES[$mstItemId] ?? null;
    }
}
