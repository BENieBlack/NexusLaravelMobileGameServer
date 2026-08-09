<?php

namespace App\Models\Mst;

/**
 * MstGacha Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property int $sort_desc
 * @property bool $is_active
 * @property string|null $start_at
 * @property string|null $end_at
 * @property int $daily_limit
 * @property bool $has_step_up
 */
class MstGacha extends _BaseMst
{
    public $table = 'mst_gacha';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var array<int, string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'sort_desc',
        'is_active',
        'start_at',
        'end_at',
        'daily_limit',
        'has_step_up',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deploy_key' => 'integer',
        'sort_desc' => 'integer',
        'is_active' => 'boolean',
        'daily_limit' => 'integer',
        'has_step_up' => 'boolean',
    ];

    public $timestamps = true;

    public function getSortDesc(): int
    {
        return $this->getAttribute('sort_desc');
    }

    public function getIsActive(): bool
    {
        return $this->getAttribute('is_active');
    }

    public function getStartAt(): ?string
    {
        return $this->getAttribute('start_at');
    }

    public function getEndAt(): ?string
    {
        return $this->getAttribute('end_at');
    }

    public function getDailyLimit(): int
    {
        return $this->getAttribute('daily_limit');
    }

    public function getHasStepUp(): bool
    {
        return $this->getAttribute('has_step_up');
    }
}
