<?php

namespace App\Models\Log;

/**
 * LogLoginBonusReceive Model
 *
 * ログインボーナス（通常・カムバック）の受け取りログ（受け取った報酬1件につき1行）
 *
 * @property int $id
 * @property int $sys_player_id
 * @property string $type
 * @property string $mst_login_bonus_id
 * @property int $day
 * @property int|null $absent_days
 * @property string $received_at
 * @property string $reward_type
 * @property string $reward_mst_id
 * @property int $reward_amount
 * @property bool $is_paid
 * @property string $created_at
 */
class LogLoginBonusReceive extends _BaseLog
{
    protected $table = 'log_action_login_bonus_receive';

    /**
     * updated_at カラムなし（created_at はDBの既定値で入る）
     */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'type',
        'mst_login_bonus_id',
        'day',
        'absent_days',
        'received_at',
        'reward_type',
        'reward_mst_id',
        'reward_amount',
        'is_paid',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'id' => 'integer',
        'sys_player_id' => 'integer',
        'day' => 'integer',
        'absent_days' => 'integer',
        'reward_amount' => 'integer',
        'is_paid' => 'boolean',
    ];

    public function getSysPlayerId(): int
    {
        return (int) $this->getAttribute('sys_player_id');
    }

    public function getType(): string
    {
        return (string) $this->getAttribute('type');
    }

    public function getMstLoginBonusId(): string
    {
        return (string) $this->getAttribute('mst_login_bonus_id');
    }

    public function getDay(): int
    {
        return (int) $this->getAttribute('day');
    }

    public function getAbsentDays(): ?int
    {
        $absentDays = $this->getAttribute('absent_days');

        return $absentDays === null ? null : (int) $absentDays;
    }

    /**
     * 受け取り日時（Y-m-d H:i:s形式）
     */
    public function getReceivedAt(): string
    {
        return (string) $this->getDateAttributeString('received_at');
    }

    public function getRewardType(): string
    {
        return (string) $this->getAttribute('reward_type');
    }

    public function getRewardMstId(): string
    {
        return (string) $this->getAttribute('reward_mst_id');
    }

    public function getRewardAmount(): int
    {
        return (int) $this->getAttribute('reward_amount');
    }

    public function getIsPaid(): bool
    {
        return (bool) $this->getAttribute('is_paid');
    }
}
