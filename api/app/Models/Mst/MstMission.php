<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MstMission Model
 *
 * ミッション定義
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $trigger_type
 * @property string|null $mst_tag_id
 * @property int $target_value
 * @property string $start_at
 * @property string|null $end_at
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class MstMission extends _BaseMst
{
    public $table = 'mst_mission';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'trigger_type',
        'mst_tag_id',
        'target_value',
        'start_at',
        'end_at',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'target_value' => 'integer',
        'sort_order' => 'integer',
    ];

    public $timestamps = true;

    /**
     * @return HasMany<MstMissionL10n, $this>
     */
    public function l10n(): HasMany
    {
        return $this->hasMany(MstMissionL10n::class, 'mst_mission_id', 'id');
    }

    public function getId(): string
    {
        return (string) $this->getAttribute('id');
    }

    public function getTriggerType(): string
    {
        return (string) $this->getAttribute('trigger_type');
    }

    public function getMstTagId(): ?string
    {
        $mstTagId = $this->getAttribute('mst_tag_id');

        return $mstTagId === null ? null : (string) $mstTagId;
    }

    public function getTargetValue(): int
    {
        return (int) $this->getAttribute('target_value');
    }

    /**
     * 開始日時（Y-m-d H:i:s形式）
     */
    public function getStartAt(): string
    {
        return (string) $this->getDateAttributeString('start_at');
    }

    /**
     * 終了日時（Y-m-d H:i:s形式、NULLは無期限）
     */
    public function getEndAt(): ?string
    {
        return $this->getDateAttributeString('end_at');
    }

    public function getSortOrder(): int
    {
        return (int) $this->getAttribute('sort_order');
    }
}
