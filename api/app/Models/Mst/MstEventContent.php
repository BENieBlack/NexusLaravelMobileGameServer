<?php

namespace App\Models\Mst;

use App\Traits\CompositePrimaryKeyTrait;

/**
 * MstEventContent Model
 *
 * イベントに束ねるコンテンツ（ガチャ・ミッション・交換所など）
 *
 * @property int $deploy_key
 * @property string $mst_event_id
 * @property string $content_type
 * @property string $content_mst_id
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 */
class MstEventContent extends _BaseMst
{
    use CompositePrimaryKeyTrait;

    public $table = 'mst_event_content';

    public $incrementing = false;

    protected $keyType = 'string';

    /** テーブル定義どおりの複合主キー */
    protected $primaryKey = ['mst_event_id', 'content_type', 'content_mst_id'];

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_event_id',
        'content_type',
        'content_mst_id',
        'sort_order',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'sort_order' => 'integer',
    ];

    public $timestamps = true;

    public function getMstEventId(): string
    {
        return (string) $this->getAttribute('mst_event_id');
    }

    public function getContentType(): string
    {
        return (string) $this->getAttribute('content_type');
    }

    public function getContentMstId(): string
    {
        return (string) $this->getAttribute('content_mst_id');
    }

    public function getSortOrder(): int
    {
        return (int) $this->getAttribute('sort_order');
    }
}
