<?php

namespace App\Models\Mst;

use App\Traits\CompositePrimaryKeyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MstTagL10n Model
 *
 * タグの表示名（多言語）
 *
 * @property int $deploy_key
 * @property string $mst_tag_id
 * @property string $language
 * @property string $name
 * @property string $created_at
 * @property string $updated_at
 */
class MstTagL10n extends _BaseMst
{
    use CompositePrimaryKeyTrait;

    public $table = 'mst_tag__l10n';

    public $incrementing = false;

    protected $keyType = 'string';

    /** テーブル定義どおりの複合主キー */
    protected $primaryKey = ['mst_tag_id', 'language'];

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_tag_id',
        'language',
        'name',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
    ];

    public $timestamps = true;

    /**
     * @return BelongsTo<MstTag, $this>
     */
    public function tag(): BelongsTo
    {
        return $this->belongsTo(MstTag::class, 'mst_tag_id', 'id');
    }

    public function getMstTagId(): string
    {
        return (string) $this->getAttribute('mst_tag_id');
    }

    public function getLanguage(): string
    {
        return (string) $this->getAttribute('language');
    }

    public function getName(): string
    {
        return (string) $this->getAttribute('name');
    }
}
