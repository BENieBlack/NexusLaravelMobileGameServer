<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MstMessageI18n Model
 * 
 * @property int $deploy_key
 * @property string $mst_message_id
 * @property string $language
 * @property string $title
 * @property string $body
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class MstMessageI18n extends _BaseMst
{
    public $table = 'mst_message__i18n';

    public $incrementing = false;

    /** @var array<int, string> */
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
    protected $casts = [
        'deploy_key' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
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
     *
     * @return BelongsTo
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(MstMessage::class, 'mst_message_id', 'id');
    }

    /**
     * レスポンス用配列に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
