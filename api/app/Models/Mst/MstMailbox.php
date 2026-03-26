<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MstMailbox Model
 * 
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_message_id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class MstMailbox extends _BaseMst
{
    public $table = 'mst_mailbox';

    public $incrementing = false;
    protected $keyType = 'string';

    /** @var array<int, string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'mst_message_id',
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
     * メッセージとのリレーション
     *
     * @return BelongsTo
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(MstMessage::class, 'mst_message_id', 'id');
    }

    /**
     * コンテンツとのリレーション
     *
     * @return HasMany
     */
    public function contentCollection(): HasMany
    {
        return $this->hasMany(MstMailboxContent::class, 'mst_mailbox_id', 'id');
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
