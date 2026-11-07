<?php

namespace NexusEvent\Constants;

/**
 * EventPhase
 *
 * イベントの期間の段階
 *
 * start_at ── end_at ── exchange_end_at
 *   開催前 │ 開催中 │ 交換期間中 │ 終了
 */
final class EventPhase
{
    /** start_at より前 */
    public const BEFORE = 'before';

    /** start_at 以上 end_at 以下（プレイ期間） */
    public const PLAYING = 'playing';

    /** end_at より後 exchange_end_at 以下（交換・受け取りの期間） */
    public const EXCHANGING = 'exchanging';

    /** exchange_end_at より後 */
    public const CLOSED = 'closed';
}
