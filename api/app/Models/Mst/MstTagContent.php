<?php

namespace App\Models\Mst;

use App\Traits\CompositePrimaryKeyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MstTagContent Model
 *
 * タグとマスター（ユニット・アイテム等）の紐付け（多対多）
 *
 * @property int $deploy_key
 * @property string $mst_tag_id
 * @property string $content_type
 * @property string $content_mst_id
 * @property string $created_at
 * @property string $updated_at
 */
class MstTagContent extends _BaseMst
{
    use CompositePrimaryKeyTrait;

    public $table = 'mst_tag_content';

    public $incrementing = false;

    protected $keyType = 'string';

    /** テーブル定義どおりの複合主キー */
    protected $primaryKey = ['mst_tag_id', 'content_type', 'content_mst_id'];

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_tag_id',
        'content_type',
        'content_mst_id',
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

    public function getContentType(): string
    {
        return (string) $this->getAttribute('content_type');
    }

    public function getContentMstId(): string
    {
        return (string) $this->getAttribute('content_mst_id');
    }
}
