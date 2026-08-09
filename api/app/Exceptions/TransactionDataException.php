<?php

namespace App\Exceptions;

/**
 * TransactionDataException
 *
 * トランザクションデータ（trx1/trx2 database, sys database）が見つからない場合の例外クラス
 * 静的ファクトリーメソッドでリソースごとの例外を生成
 *
 * 使用例:
 * - TrxUnit, TrxItem, SysPlayer などが見つからない場合
 * - プレイヤー所有データの不整合検出に使用
 */
class TransactionDataException extends GameException
{
    /**
     * プレイヤーが見つからない
     *
     * @param  int  $playerId  プレイヤーID
     */
    public static function player(int $playerId): self
    {
        return new self(
            GameErrorCode::PLAYER_NOT_FOUND,
            "Player not found: {$playerId}"
        );
    }

    /**
     * プレイヤー（UUID）が見つからない
     *
     * @param  string  $uuid  プレイヤーUUID
     */
    public static function playerByUuid(string $uuid): self
    {
        return new self(
            GameErrorCode::PLAYER_NOT_FOUND,
            "Player not found by UUID: {$uuid}"
        );
    }

    /**
     * プレイヤー（My ID）が見つからない
     *
     * @param  string  $myId  プレイヤーMy ID
     */
    public static function playerByMyId(string $myId): self
    {
        return new self(
            GameErrorCode::PLAYER_NOT_FOUND,
            "Player not found by My ID: {$myId}"
        );
    }

    /**
     * ユニットが見つからない
     *
     * @param  int  $unitId  ユニットID
     */
    public static function unit(int $unitId): self
    {
        return new self(
            GameErrorCode::UNIT_NOT_FOUND,
            "Unit not found: {$unitId}"
        );
    }

    /**
     * アイテムが見つからない
     *
     * @param  string  $itemId  アイテムID
     */
    public static function item(string $itemId): self
    {
        return new self(
            GameErrorCode::ITEM_NOT_ENOUGH,
            "Item not found: {$itemId}"
        );
    }

    /**
     * 装備が見つからない
     *
     * @param  int  $equipmentId  装備ID
     */
    public static function equipment(int $equipmentId): self
    {
        return new self(
            GameErrorCode::EQUIPMENT_NOT_FOUND,
            "Equipment not found: {$equipmentId}"
        );
    }

    /**
     * ウォレットが見つからない
     *
     * @param  string  $itemId  アイテムID
     */
    public static function wallet(string $itemId): self
    {
        return new self(
            GameErrorCode::WALLET_NOT_FOUND,
            "Wallet not found for item: {$itemId}"
        );
    }

    /**
     * ダイヤモンドデータが見つからない
     *
     * @param  int  $playerId  プレイヤーID
     * @param  string  $platform  プラットフォーム
     */
    public static function diamond(int $playerId, string $platform): self
    {
        return new self(
            GameErrorCode::DIAMOND_NOT_ENOUGH,
            "Diamond data not found: player={$playerId}, platform={$platform}"
        );
    }

    /**
     * スタミナデータが見つからない
     *
     * @param  int  $playerId  プレイヤーID
     */
    public static function stamina(int $playerId): self
    {
        return new self(
            GameErrorCode::STAMINA_NOT_ENOUGH,
            "Stamina data not found: player={$playerId}"
        );
    }

    /**
     * 汎用トランザクションデータが見つからない
     *
     * @param  string  $type  データ種別（例: "unit", "item", "player"）
     * @param  string|int  $id  データID
     */
    public static function generic(string $type, string|int $id): self
    {
        return new self(
            GameErrorCode::INTERNAL_ERROR,
            "Transaction data not found: {$type} (ID: {$id})"
        );
    }
}
