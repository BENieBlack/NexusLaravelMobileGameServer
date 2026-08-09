<?php

namespace App\Exceptions;

/**
 * BusinessLogicException
 *
 * ビジネスロジックエラーを表す例外クラス
 * バリデーションは通過したが、ビジネスルール上エラーとなる場合に使用
 */
class BusinessLogicException extends GameException
{
    /**
     * スタミナ不足
     *
     * @param  int  $required  必要なスタミナ
     * @param  int  $current  現在のスタミナ
     */
    public static function staminaNotEnough(int $required, int $current): self
    {
        return new self(
            GameErrorCode::STAMINA_NOT_ENOUGH,
            "Stamina not enough. Required: {$required}, Current: {$current}"
        );
    }

    /**
     * ダイヤモンド不足
     *
     * @param  int  $required  必要なダイヤモンド
     * @param  int  $current  現在のダイヤモンド
     */
    public static function diamondNotEnough(int $required, int $current): self
    {
        return new self(
            GameErrorCode::DIAMOND_NOT_ENOUGH,
            "Diamond not enough. Required: {$required}, Current: {$current}"
        );
    }

    /**
     * アイテム不足
     *
     * @param  string  $itemId  アイテムID
     * @param  int  $required  必要な数量
     * @param  int  $current  現在の数量
     */
    public static function itemNotEnough(string $itemId, int $required, int $current): self
    {
        return new self(
            GameErrorCode::ITEM_NOT_ENOUGH,
            "Item not enough: {$itemId}. Required: {$required}, Current: {$current}"
        );
    }

    /**
     * 通貨不足
     *
     * @param  string  $currencyId  通貨ID
     * @param  int  $required  必要な金額
     * @param  int  $current  現在の金額
     */
    public static function insufficientCurrency(string $currencyId, int $required, int $current): self
    {
        return new self(
            GameErrorCode::INSUFFICIENT_CURRENCY,
            "Insufficient currency: {$currencyId}. Required: {$required}, Current: {$current}"
        );
    }

    /**
     * 不正なアイテムタイプ
     *
     * @param  string  $itemId  アイテムID
     * @param  string  $expectedType  期待されるタイプ
     * @param  string  $actualType  実際のタイプ
     */
    public static function invalidItemType(string $itemId, string $expectedType, string $actualType): self
    {
        return new self(
            GameErrorCode::INVALID_ITEM_TYPE,
            "Invalid item type: {$itemId}. Expected: {$expectedType}, Actual: {$actualType}"
        );
    }

    /**
     * ユニット最大レベル到達
     *
     * @param  int  $unitId  ユニットID
     * @param  int  $maxLevel  最大レベル
     */
    public static function unitMaxLevelReached(int $unitId, int $maxLevel): self
    {
        return new self(
            GameErrorCode::UNIT_MAX_LEVEL_REACHED,
            "Unit already at max level: {$unitId} (Level {$maxLevel})"
        );
    }

    /**
     * 購入制限超過
     *
     * @param  string  $productId  商品ID
     * @param  int  $limit  購入制限
     */
    public static function purchaseLimitExceeded(string $productId, int $limit): self
    {
        return new self(
            GameErrorCode::PURCHASE_LIMIT_EXCEEDED,
            "Purchase limit exceeded for product: {$productId} (Limit: {$limit})"
        );
    }

    /**
     * 商品が無効
     *
     * @param  string  $productId  商品ID
     */
    public static function productInactive(string $productId): self
    {
        return new self(
            GameErrorCode::PRODUCT_INACTIVE,
            "Product is inactive: {$productId}"
        );
    }

    /**
     * 不正なリソースタイプ
     *
     * @param  string  $resourceType  リソースタイプ
     */
    public static function invalidResourceType(string $resourceType): self
    {
        return new self(
            GameErrorCode::INVALID_RESOURCE_TYPE,
            "Invalid resource type: {$resourceType}"
        );
    }

    /**
     * 不正な商品タイプ
     *
     * @param  string  $productType  商品タイプ
     */
    public static function invalidProductType(string $productType): self
    {
        return new self(
            GameErrorCode::INVALID_PRODUCT_TYPE,
            "Invalid product type: {$productType}"
        );
    }

    /**
     * デバイスID既に登録済み
     *
     * @param  string  $deviceId  デバイスID
     */
    public static function deviceAlreadyExists(string $deviceId): self
    {
        return new self(
            GameErrorCode::DEVICE_ALREADY_EXISTS,
            "Device ID already registered: {$deviceId}. Please use sign_in endpoint."
        );
    }
}
