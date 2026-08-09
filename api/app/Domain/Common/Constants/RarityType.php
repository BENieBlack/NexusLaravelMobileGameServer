<?php

namespace App\Domain\Common\Constants;

/**
 * ゲーム要素のレアリティ（希少度）定数
 *
 * ユニット、装備、アイテムなど、ゲーム内の様々な要素に共通するレアリティ定義
 * このゲームタイトル固有のドメイン知識
 */
class RarityType
{
    /**
     * UR - Ultra Rare（最高レア）
     */
    const UR = 'UR';

    /**
     * SSR - Super Super Rare
     */
    const SSR = 'SSR';

    /**
     * SR - Super Rare
     */
    const SR = 'SR';

    /**
     * R - Rare
     */
    const R = 'R';

    /**
     * UC - Uncommon
     */
    const UC = 'UC';

    /**
     * C - Common（最低レア）
     */
    const C = 'C';

    /**
     * 全レアリティの配列を取得
     *
     * 高レア度順（UR → C）で返す
     */
    public static function getAll(): array
    {
        return [
            self::UR,
            self::SSR,
            self::SR,
            self::R,
            self::UC,
            self::C,
        ];
    }

    /**
     * レアリティが有効かチェック
     */
    public static function isValid(string $rarity): bool
    {
        return in_array($rarity, self::getAll(), true);
    }

    /**
     * レアリティのランク値を取得
     *
     * UR=6, SSR=5, SR=4, R=3, UC=2, C=1
     * ソート・比較に使用可能
     *
     * @return int|null ランク値（無効な場合はnull）
     */
    public static function getRank(string $rarity): ?int
    {
        return match ($rarity) {
            self::UR => 6,
            self::SSR => 5,
            self::SR => 4,
            self::R => 3,
            self::UC => 2,
            self::C => 1,
            default => null,
        };
    }

    /**
     * レアリティを比較
     *
     * @return int 1=rarity1が高い, 0=同じ, -1=rarity2が高い, null=無効な値
     */
    public static function compare(string $rarity1, string $rarity2): ?int
    {
        $rank1 = self::getRank($rarity1);
        $rank2 = self::getRank($rarity2);

        if ($rank1 === null || $rank2 === null) {
            return null;
        }

        return $rank1 <=> $rank2;
    }
}
