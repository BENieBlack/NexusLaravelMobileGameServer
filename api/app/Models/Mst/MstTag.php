<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MstTag Model
 *
 * タグ定義（作品・種別・属性など）
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $tag_category
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class MstTag extends _BaseMst
{
    public $table = 'mst_tag';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'tag_category',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'sort_order' => 'integer',
    ];

    public $timestamps = true;

    /**
     * @return HasMany<MstTagL10n, $this>
     */
    public function l10n(): HasMany
    {
        return $this->hasMany(MstTagL10n::class, 'mst_tag_id', 'id');
    }

    public function getId(): string
    {
        return (string) $this->getAttribute('id');
    }

    public function getTagCategory(): string
    {
        return (string) $this->getAttribute('tag_category');
    }

    public function getSortOrder(): int
    {
        return (int) $this->getAttribute('sort_order');
    }
}
