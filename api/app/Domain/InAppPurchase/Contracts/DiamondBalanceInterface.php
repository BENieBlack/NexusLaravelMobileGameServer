<?php

namespace App\Domain\InAppPurchase\Contracts;

/**
 * DiamondBalanceInterface
 *
 * InAppPurchaseモジュールの公開API。ダイヤ残高を増減する。
 *
 * 無償ダイヤは trx_item の free_diamond、有償ダイヤは trx_diamond（X-Platform の行）で持つ。
 * 無償の付与はアイテムの付与経路（item / free_diamond）に一本化したので、ここには無い。
 */
interface DiamondBalanceInterface
{
    /**
     * 有償ダイヤを付与する（trx_diamond.paid_amount のみ。trx_diamond_balance の行は作らない）
     *
     * @param  string  $platform  プラットフォーム（Apple, Google）
     */
    public function addPaidDiamond(int $sysPlayerId, string $platform, int $amount): void;

    /**
     * ダイヤの所持数を返す（無償は platform 共通、有償は X-Platform の行だけ）
     *
     * @param  bool  $isPaidOnly  有償ダイヤだけを数えるか（falseの場合は無償と有償の合計）
     */
    public function findDiamondAmount(int $sysPlayerId, bool $isPaidOnly = false): int;

    /**
     * ダイヤを消費する
     *
     * @param  bool  $isPaidOnly  有償ダイヤのみを消費するか（falseの場合は無償→有償の順）
     *
     * @throws \Exception 残高不足の場合
     */
    public function consumeDiamond(int $sysPlayerId, int $amount, bool $isPaidOnly = false): void;
}
