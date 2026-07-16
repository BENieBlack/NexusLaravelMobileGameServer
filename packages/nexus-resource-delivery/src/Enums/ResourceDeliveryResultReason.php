<?php

namespace NexusResourceDelivery\Enums;

/**
 * ResourceDeliveryResultReason
 *
 * リソース配送コンテンツの状態理由を表すEnum
 * 変換理由と配送失敗理由を統合
 *
 * 変換理由の例：
 * - 重複ユニット → アイテムに変換
 * - 重複装備 → 別のリソースに変換
 * - アイテムボックス → 実リソースに変換
 *
 * 配送失敗理由の例：
 * - 所持上限に達している
 * - メールボックスに送信された
 * - その他のエラー
 */
enum ResourceDeliveryResultReason: string
{
    // デフォルト（変換なし、配送成功）
    case NONE = 'none';

    // 変換理由
    case DUPLICATED_UNIT = 'duplicated_unit';           // 重複ユニット
    case DUPLICATED_EQUIPMENT = 'duplicated_equipment'; // 重複装備
    case BOX_ITEM = 'box_item';                         // ボックスアイテム展開

    // 配送失敗理由
    case RESOURCE_LIMIT_REACHED = 'resource_limit_reached'; // リソース上限到達
    case SEND_TO_MAILBOX = 'send_to_mailbox';               // メールボックスに送信
    case INVENTORY_FULL = 'inventory_full';                 // インベントリ満杯

    // その他
    case OTHER = 'other';                                   // その他

    /**
     * 変換理由かどうかを判定
     */
    public function isConversionReason(): bool
    {
        return in_array($this, [
            self::DUPLICATED_UNIT,
            self::DUPLICATED_EQUIPMENT,
            self::BOX_ITEM,
        ], true);
    }

    /**
     * 配送失敗理由かどうかを判定
     */
    public function isFailureReason(): bool
    {
        return in_array($this, [
            self::RESOURCE_LIMIT_REACHED,
            self::SEND_TO_MAILBOX,
            self::INVENTORY_FULL,
            self::OTHER,
        ], true);
    }
}
