<?php

namespace NexusEvent\Constants;

/**
 * EventContentType
 *
 * イベントに束ねられるコンテンツの種別（mst_event_content.content_type）
 *
 * コンテンツは自分の start_at / end_at を持っていても、イベントに入っていれば
 * イベントの期間で上書きされる。種別ごとに、イベントのどの時刻を子の end_at として使うかが決まる。
 */
final class EventContentType
{
    public const GACHA = 'gacha';

    public const LOGIN_BONUS = 'login_bonus';

    public const REWARD_TRACK = 'reward_track';

    /** 交換所（mst_trade） */
    public const TRADE = 'trade';

    public const MISSION = 'mission';

    public const RANKING = 'ranking';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::GACHA,
            self::LOGIN_BONUS,
            self::REWARD_TRACK,
            self::TRADE,
            self::MISSION,
            self::RANKING,
        ];
    }

    /**
     * 子の end_at に、イベントの exchange_end_at を使う種別か
     *
     * 交換所は交換期間まで使え、ミッションは報酬の受け取りを交換期間まで受け付ける。
     * それ以外は、イベントの end_at（プレイ期間の終わり）を使う。
     */
    public static function usesExchangeEndAsEndAt(string $contentType): bool
    {
        return $contentType === self::TRADE || $contentType === self::MISSION;
    }
}
