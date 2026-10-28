<?php

namespace App\Models\Trx;

use App\Traits\CompositePrimaryKeyTrait;

/**
 * TrxProgress Model
 *
 * 出来事 × 対象（タグ）ごとの通算カウンタ
 *
 * ミッションに依存せず数え、ミッションは (trigger_type, sub_target_id) で参照する。
 * (sys_player_id, trigger_type, sub_target_id) で一意。
 *
 * @property int $sys_player_id
 * @property string $trigger_type
 * @property string $sub_target_id
 * @property int $progress_value
 * @property bool $is_delete
 * @property string $created_at
 * @property string $updated_at
 */
class TrxProgress extends _BaseTrx
{
    use CompositePrimaryKeyTrait;

    protected $table = 'trx_progress';

    /**
     * 採番idは持たず、業務上の一意をそのまま主キーにする
     */
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = ['sys_player_id', 'trigger_type', 'sub_target_id'];

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     *
     * @var list<string>
     */
    protected array $selectKeys = ['sys_player_id'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'trigger_type',
        'sub_target_id',
        'progress_value',
        'is_delete',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'progress_value' => 'integer',
        'is_delete' => 'boolean',
    ];

    public function getSysPlayerId(): int
    {
        return (int) $this->getAttribute('sys_player_id');
    }

    public function getTriggerType(): string
    {
        return (string) $this->getAttribute('trigger_type');
    }

    /**
     * 数える対象（mst_tag.id か all）
     */
    public function getSubTargetId(): string
    {
        return (string) $this->getAttribute('sub_target_id');
    }

    public function getProgressValue(): int
    {
        return (int) $this->getAttribute('progress_value');
    }

    public function setProgressValue(int $progressValue): void
    {
        $this->setAttribute('progress_value', $progressValue);
    }

    public function getIsDelete(): bool
    {
        return (bool) $this->getAttribute('is_delete');
    }
}
