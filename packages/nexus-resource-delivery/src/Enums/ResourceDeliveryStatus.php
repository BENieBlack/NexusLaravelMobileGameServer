<?php

namespace NexusResourceDelivery\Enums;

/**
 * ResourceDeliveryStatus
 *
 * リソース配送コンテンツの状態を表すEnum
 *
 * 状態遷移:
 * - 即時配布: PENDING → RECEIVED（直接受取）
 * - メールボックス: PENDING → DELIVERED（送信済み・未受取）→ RECEIVED（受取済み）
 *
 * - PENDING: 未送信（DeliveryManagerに追加されただけで、まだ配送処理を実行していない）
 * - DELIVERED: 配送完了（メールボックスに送信済み、プレイヤーはまだ受け取っていない）
 * - RECEIVED: 受取完了（プレイヤーが実際に受け取った状態）
 */
enum ResourceDeliveryStatus: string
{
    case PENDING = 'pending';       // 未送信
    case DELIVERED = 'delivered';   // 配送完了（メールボックス送信済み・未受取）
    case RECEIVED = 'received';     // 受取完了
}
