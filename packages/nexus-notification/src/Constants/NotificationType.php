<?php

namespace NexusNotification\Constants;

/**
 * NotificationType
 *
 * ゲーム内通知の種別定数
 * 各パッケージが通知を送信する際に使用する
 */
enum NotificationType: string
{
    // ミッション/クエスト系
    case MISSION_COMPLETED = 'mission_completed';
    case MISSION_REWARD_READY = 'mission_reward_ready';

    // フレンド系
    case FRIEND_APPLY_RECEIVED = 'friend_apply_received';
    case FRIEND_APPLY_ACCEPTED = 'friend_apply_accepted';

    // ギルド系
    case GUILD_APPLY_RECEIVED = 'guild_apply_received';
    case GUILD_APPLY_ACCEPTED = 'guild_apply_accepted';
    case GUILD_APPLY_REJECTED = 'guild_apply_rejected';

    // メールボックス系
    case MAILBOX_RECEIVED = 'mailbox_received';

    // ログインボーナス系
    case LOGIN_BONUS_READY = 'login_bonus_ready';

    // バトル系（将来実装予定）
    case BATTLE_RESULT = 'battle_result';
    case GUILD_VS_GUILD_RESULT = 'guild_vs_guild_result';

    // システム系
    case SYSTEM_ANNOUNCEMENT = 'system_announcement';
    case MAINTENANCE_NOTICE = 'maintenance_notice';
}
