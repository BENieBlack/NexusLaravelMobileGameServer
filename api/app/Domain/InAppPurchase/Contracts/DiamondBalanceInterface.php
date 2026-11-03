<?php

namespace App\Domain\InAppPurchase\Contracts;

/**
 * DiamondBalanceInterface
 *
 * InAppPurchaseモジュールの公開API。ダイヤ残高を増減する。
 */
interface DiamondBalanceInterface
{
    /**
     * ダイヤを付与する
     *
     * @param  string  $platform  プラットフォーム（Apple, Google）
     * @param  bool  $isPaid  有償ダイヤか（falseの場合は無償）
     */
    public function addDiamond(int $sysPlayerId, string $platform, int $amount, bool $isPaid = false): void;

    /**
     * 全プラットフォームを合わせたダイヤの所持数を返す
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
