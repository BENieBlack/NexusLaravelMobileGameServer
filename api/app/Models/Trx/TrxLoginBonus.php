<?php

namespace App\Models\Trx;

use App\Traits\CompositePrimaryKeyTrait;

/**
 * TrxLoginBonus
 *
 * ログインボーナスの最新の受け取り状態（プレイヤー×タイプごとに1行）
 *
 * 受け取りの履歴は log_action_login_bonus_receive（LogLoginBonusReceive）に残す。
 * is_delete カラムなし（読み取りの論理削除除外は TrxLoginBonusRepository で切る）
 *
 * @property int $sys_player_id
 * @property string $type
 * @property string $mst_login_bonus_id
 * @property int $day
 * @property int|null $absent_days
 * @property string $received_date
 * @property string $created_at
 * @property string $updated_at
 */
class TrxLoginBonus extends _BaseTrx
{
    use CompositePrimaryKeyTrait;

    protected $table = 'trx_login_bonus';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = ['sys_player_id', 'type'];

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     *
     * @var list<string>
     */
    protected array $selectKeys = ['sys_player_id'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'type',
        'mst_login_bonus_id',
        'day',
        'absent_days',
        'received_date',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'day' => 'integer',
        'absent_days' => 'integer',
    ];

    public function getSysPlayerId(): int
    {
        return (int) $this->getAttribute('sys_player_id');
    }

    /**
     * ログインボーナスのタイプ（daily / comeback）
     */
    public function getType(): string
    {
        return (string) $this->getAttribute('type');
    }

    public function getMstLoginBonusId(): string
    {
        return (string) $this->getAttribute('mst_login_bonus_id');
    }

    public function setMstLoginBonusId(string $mstLoginBonusId): void
    {
        $this->setAttribute('mst_login_bonus_id', $mstLoginBonusId);
    }

    public function getDay(): int
    {
        return (int) $this->getAttribute('day');
    }

    public function setDay(int $day): void
    {
        $this->setAttribute('day', $day);
    }

    public function getAbsentDays(): ?int
    {
        $absentDays = $this->getAttribute('absent_days');

        return $absentDays === null ? null : (int) $absentDays;
    }

    public function setAbsentDays(?int $absentDays): void
    {
        $this->setAttribute('absent_days', $absentDays);
    }

    /**
     * 最後に受け取った日時（Y-m-d H:i:s形式）
     */
    public function getReceivedDate(): string
    {
        return (string) $this->getDateAttributeString('received_date');
    }

    public function setReceivedDate(string $receivedDate): void
    {
        $this->setAttribute('received_date', $receivedDate);
    }
}
