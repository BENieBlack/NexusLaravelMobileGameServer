<?php

namespace App\Models\Trx;

use App\Traits\CompositePrimaryKeyTrait;

/**
 * TrxMission Model
 *
 * ミッションの報酬を受け取った記録
 *
 * 達成したかどうかは trx_progress から求めるので持たない。
 * (sys_player_id, mst_mission_id) で一意。受け取ったときに作られる。
 *
 * @property int $sys_player_id
 * @property string $mst_mission_id
 * @property string $received_at
 * @property bool $is_delete
 * @property string $created_at
 * @property string $updated_at
 */
class TrxMission extends _BaseTrx
{
    use CompositePrimaryKeyTrait;

    protected $table = 'trx_mission';

    /**
     * 採番idは持たず、業務上の一意をそのまま主キーにする
     */
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = ['sys_player_id', 'mst_mission_id'];

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     *
     * @var list<string>
     */
    protected array $selectKeys = ['sys_player_id'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'mst_mission_id',
        'received_at',
        'is_delete',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'is_delete' => 'boolean',
    ];

    public function getSysPlayerId(): int
    {
        return (int) $this->getAttribute('sys_player_id');
    }

    public function getMstMissionId(): string
    {
        return (string) $this->getAttribute('mst_mission_id');
    }

    /**
     * 受け取った日時（Y-m-d H:i:s形式）
     */
    public function getReceivedAt(): string
    {
        return (string) $this->getDateAttributeString('received_at');
    }

    public function getIsDelete(): bool
    {
        return (bool) $this->getAttribute('is_delete');
    }
}
