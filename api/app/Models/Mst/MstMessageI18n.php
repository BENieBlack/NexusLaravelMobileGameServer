<?php

namespace App\Models\Mst;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MstMessageI18n Model
 *
 * @property int $deploy_key
 * @property string $mst_message_id
 * @property string $language
 * @property string $title
 * @property string $body
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class MstMessageI18n extends _BaseMst
{
    public $table = 'mst_message__i18n';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_message_id',
        'language',
        'title',
        'body',
    ];

    /**
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
    ];

    public $timestamps = true;

    /**
     * 複合主キーを取得
     *
     * @return array<int, string>
     */
    public function getKeyName(): array
    {
        return ['mst_message_id', 'language'];
    }

    /**
     * メッセージマスターとのリレーション
     */
    /**
     * @return BelongsTo<MstMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(MstMessage::class, 'mst_message_id', 'id');
    }

    /**
     * レスポンス用配列に変換
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
