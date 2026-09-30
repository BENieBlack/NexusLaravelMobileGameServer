<?php

namespace NexusChat\Constants;

/**
 * ChatRoomType
 *
 * チャットルームの種別定数
 */
enum ChatRoomType: string
{
    // フレンド間のダイレクトメッセージ（2人固定）
    case FRIEND = 'friend';

    // ギルド全体チャット（ギルド加入で自動参加）
    case GUILD = 'guild';

    // 招待制グループチャット（最大20人、Owner/Adminが招待）
    case GROUP = 'group';
}
