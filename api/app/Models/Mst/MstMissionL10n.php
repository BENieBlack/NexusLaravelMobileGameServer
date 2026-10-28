<?php

namespace App\Models\Mst;

use App\Traits\CompositePrimaryKeyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MstMissionL10n Model
 *
 * ミッションの表示名・説明（多言語）
 *
 * @property int $deploy_key
 * @property string $mst_mission_id
 * @property string $language
 * @property string $name
 * @property string|null $description
 * @property string $created_at
 * @property string $updated_at
 */
class MstMissionL10n extends _BaseMst
{
    use CompositePrimaryKeyTrait;

    public $table = 'mst_mission__l10n';

    public $incrementing = false;

    protected $keyType = 'string';

    /** テーブル定義どおりの複合主キー */
    protected $primaryKey = ['mst_mission_id', 'language'];

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_mission_id',
        'language',
        'name',
        'description',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
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

    public function getLanguage(): string
    {
        return (string) $this->getAttribute('language');
    }

    public function getName(): string
    {
        return (string) $this->getAttribute('name');
    }

    public function getDescription(): ?string
    {
        $description = $this->getAttribute('description');

        return $description === null ? null : (string) $description;
    }
}
