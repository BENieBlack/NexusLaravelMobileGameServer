<?php

namespace NexusTrade\Constants;

/**
 * TradeResetType
 *
 * 交換回数を数え直す周期（mst_trade_product.reset_type）
 *
 * 周期の境界はゲーム内日付（ClockUtility::calcGameDayStart）で切り替える。
 * 週は ISO 8601（月曜始まり）。
 */
final class TradeResetType
{
    /** 数え直さない（期間中の通算） */
    public const NONE = 'none';

    public const DAILY = 'daily';

    public const WEEKLY = 'weekly';

    public const MONTHLY = 'monthly';
}
