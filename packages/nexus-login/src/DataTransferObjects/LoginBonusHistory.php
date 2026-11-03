<?php

namespace NexusLogin\DataTransferObjects;

/**
 * LoginBonusHistory
 *
 * ログインボーナスの最新の受け取り状態（trx_login_bonus の1行。プレイヤー×タイプごとに1行）
 */
class LoginBonusHistory
{
    public function __construct(
        private readonly int $sysPlayerId,
        private readonly string $type,
        private readonly string $mstLoginBonusId,
        private readonly int $day,
        private readonly ?int $absentDays,
        private readonly string $receivedDate,
    ) {}

    public function getSysPlayerId(): int
    {
        return $this->sysPlayerId;
    }

    /**
     * ログインボーナスのタイプ（daily / comeback）
     */
    public function getType(): string
    {
        return $this->type;
    }

    public function getMstLoginBonusId(): string
    {
        return $this->mstLoginBonusId;
    }

    /**
     * 最後に受け取った日数
     */
    public function getDay(): int
    {
        return $this->day;
    }

    /**
     * 休眠日数（カムバックボーナスの場合のみ）
     */
    public function getAbsentDays(): ?int
    {
        return $this->absentDays;
    }

    /**
     * 最後に受け取った日時（Y-m-d H:i:s形式）
     */
    public function getReceivedDate(): string
    {
        return $this->receivedDate;
    }
}
