<?php

namespace App\Models\Trx;

/**
 * TrxVipLoginBonusHistory
 *
 * VIPログインボーナスの最新の受け取り状態（プレイヤーごとに1行）
 *
 * 受け取りの履歴は log_action_vip_login_bonus_receive（LogVipLoginBonusReceive）に残す。
 * is_delete カラムなし（読み取りの論理削除除外は TrxVipLoginBonusHistoryRepository で切る）
 *
 * @property int $sys_player_id プレイヤーID
 * @property string $mst_vip_login_bonus_id VIPログインボーナスID
 * @property int $day 受け取った日数
 * @property int $vip_level 受け取り時のVIPレベル
 * @property string $received_at 受け取り日時（UTC）
 * @property string $created_at
 * @property string $updated_at
 */
class TrxVipLoginBonusHistory extends _BaseTrx
{
    protected $table = 'trx_vip_login_bonus';

    /**
     * プレイヤーごとに1行なので sys_player_id が主キー
     */
    protected $primaryKey = 'sys_player_id';

    public $incrementing = false;

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     *
     * @var list<string>
     */
    protected array $selectKeys = ['sys_player_id'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'mst_vip_login_bonus_id',
        'day',
        'vip_level',
        'received_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'day' => 'integer',
        'vip_level' => 'integer',
    ];

    public function getSysPlayerId(): int
    {
        return (int) $this->getAttribute('sys_player_id');
    }

    public function getMstVipLoginBonusId(): string
    {
        return (string) $this->getAttribute('mst_vip_login_bonus_id');
    }

    public function setMstVipLoginBonusId(string $mstVipLoginBonusId): void
    {
        $this->setAttribute('mst_vip_login_bonus_id', $mstVipLoginBonusId);
    }

    public function getDay(): int
    {
        return (int) $this->getAttribute('day');
    }

    public function setDay(int $day): void
    {
        $this->setAttribute('day', $day);
    }

    public function getVipLevel(): int
    {
        return (int) $this->getAttribute('vip_level');
    }

    public function setVipLevel(int $vipLevel): void
    {
        $this->setAttribute('vip_level', $vipLevel);
    }

    /**
     * 受け取り日時（Y-m-d H:i:s形式）
     */
    public function getReceivedAt(): string
    {
        return (string) $this->getDateAttributeString('received_at');
    }

    public function setReceivedAt(string $receivedAt): void
    {
        $this->setAttribute('received_at', $receivedAt);
    }
}
