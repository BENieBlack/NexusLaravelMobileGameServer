<?php

namespace NexusChat\Constants;

/**
 * ChatRoomMemberLimit
 *
 * チャットルーム種別ごとの最大メンバー数定数
 */
final class ChatRoomMemberLimit
{
    // フレンドDMは2人固定
    public const FRIEND = 2;

    // ギルドチャットはギルドの最大人数に依存するため制限なし（ギルド側で管理）
    public const GUILD = PHP_INT_MAX;

    // 招待制グループチャットの最大人数
    public const GROUP = 20;

    private function __construct() {}
}
