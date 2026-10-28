<?php

namespace App\Models\Mst;

use App\Traits\CompositePrimaryKeyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MstMissionContent Model
 *
 * ミッションの達成報酬
 *
 * @property int $deploy_key
 * @property string $mst_mission_id
 * @property string $content_type
 * @property string $content_mst_id
 * @property int $content_quantity
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class MstMissionContent extends _BaseMst
{
    use CompositePrimaryKeyTrait;

    public $table = 'mst_mission_content';

    public $incrementing = false;

    protected $keyType = 'string';

    /** テーブル定義どおりの複合主キー */
    protected $primaryKey = ['mst_mission_id', 'content_type', 'content_mst_id'];

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_mission_id',
        'content_type',
        'content_mst_id',
        'content_quantity',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'content_quantity' => 'integer',
        'sort_order' => 'integer',
    ];

    public $timestamps = true;

    /**
     * @return BelongsTo<MstMission, $this>
     */
    public function mission(): BelongsTo
    {
        return $this->belongsTo(MstMission::class, 'mst_mission_id', 'id');
    }

    public function getMstMissionId(): string
    {
        return (string) $this->getAttribute('mst_mission_id');
    }

    public function getContentType(): string
    {
        return (string) $this->getAttribute('content_type');
    }

    public function getContentMstId(): string
    {
        return (string) $this->getAttribute('content_mst_id');
    }

    public function getContentQuantity(): int
    {
        return (int) $this->getAttribute('content_quantity');
    }

    public function getSortOrder(): int
    {
        return (int) $this->getAttribute('sort_order');
    }
}
