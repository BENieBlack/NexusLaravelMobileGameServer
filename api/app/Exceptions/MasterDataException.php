<?php

namespace App\Exceptions;

/**
 * MasterDataException
 *
 * マスターデータ（mst database）が見つからない場合の例外クラス
 * 静的ファクトリーメソッドでマスターデータごとの例外を生成
 *
 * 使用例:
 * - MstUnit, MstItem, MstUnitLevel などが見つからない場合
 * - マスター設定ミスやデータ不整合の検出に使用
 */
class MasterDataException extends GameException
{
    /**
     * ユニットマスターデータが見つからない
     *
     * @param  string  $mstUnitId  ユニットマスターID
     */
    public static function unit(string $mstUnitId): self
    {
        return new self(
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            "Master unit data not found: {$mstUnitId}"
        );
    }

    /**
     * アイテムマスターデータが見つからない
     *
     * @param  string  $mstItemId  アイテムマスターID
     */
    public static function item(string $mstItemId): self
    {
        return new self(
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            "Master item data not found: {$mstItemId}"
        );
    }

    /**
     * レベルマスターデータが見つからない
     *
     * @param  string  $rarity  レアリティ
     * @param  int  $level  レベル
     */
    public static function unitLevel(string $rarity, int $level): self
    {
        return new self(
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            "Master unit level data not found: rarity={$rarity}, level={$level}"
        );
    }

    /**
     * プレイヤーレベルマスターデータが見つからない
     *
     * @param  int  $level  レベル
     */
    public static function playerLevel(int $level): self
    {
        return new self(
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            "Master player level data not found: level={$level}"
        );
    }

    /**
     * 商品マスターデータが見つからない
     *
     * @param  string  $mstProductId  商品マスターID
     */
    public static function product(string $mstProductId): self
    {
        return new self(
            GameErrorCode::PRODUCT_NOT_FOUND,
            "Master product data not found: {$mstProductId}"
        );
    }

    /**
     * 課金商品マスターデータが見つからない
     *
     * @param  string  $mstInAppPurchaseId  課金商品マスターID
     */
    public static function inAppPurchase(string $mstInAppPurchaseId): self
    {
        return new self(
            GameErrorCode::PRODUCT_NOT_FOUND,
            "Master in-app purchase data not found: {$mstInAppPurchaseId}"
        );
    }

    /**
     * 装備マスターデータが見つからない
     *
     * @param  string  $mstEquipmentId  装備マスターID
     */
    public static function equipment(string $mstEquipmentId): self
    {
        return new self(
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            "Master equipment data not found: {$mstEquipmentId}"
        );
    }

    /**
     * 汎用マスターデータが見つからない
     *
     * @param  string  $type  マスターデータ種別（例: "unit", "item", "level"）
     * @param  string|int  $id  マスターデータID
     */
    public static function generic(string $type, string|int $id): self
    {
        return new self(
            GameErrorCode::MASTER_DATA_NOT_FOUND,
            "Master data not found: {$type} (ID: {$id})"
        );
    }
}
