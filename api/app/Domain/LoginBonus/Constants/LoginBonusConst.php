<?php

namespace App\Domain\LoginBonus\Constants;

/**
 * LoginBonusConst
 *
 * VIP ログインボーナスを再受け取りできない理由（VipLoginBonusReReceiveService::judge の戻り値に入る）
 *
 * 理由はログとテストの切り分け用。クライアントには 26001 / 26002 / 26003 のコードで返し、
 * 26001 の中の 2 つの理由（not_granted_today / level_not_raised）でクライアントの対応は変わらない。
 */
class LoginBonusConst
{
    /** ③ 今のレベルの有効な VIP ログインボーナスが無い（26002） */
    public const REASON_MASTER_NOT_FOUND = 'master_not_found';

    /** 渡された ID が今のレベルのボーナスと一致しない（26002。receive だけ） */
    public const REASON_MASTER_MISMATCH = 'master_mismatch';

    /** ① 受け取りの行が無い、または受取日が今日でない（26001） */
    public const REASON_NOT_GRANTED_TODAY = 'not_granted_today';

    /** ② 今のレベルが受取時のレベルより高くない（26001） */
    public const REASON_LEVEL_NOT_RAISED = 'level_not_raised';

    /** ④ 据え置いた日数が loop_days を超える、またはその日の報酬行が無い（26003） */
    public const REASON_REWARD_NOT_FOUND = 'reward_not_found';
}
