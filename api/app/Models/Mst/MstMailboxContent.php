<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MstMailboxContent Model
 * 
 * @property int $deploy_key
 * @property string $mst_mailbox_id
 * @property string $content_type
 * @property string $content_id
 * @property int $amount
 * @property int $sort_desc
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class MstMailboxContent extends _BaseMst
{
    public $table = 'mst_mailbox_content';

    public $incrementing = false;

    /** @var array<int, string> */
    protected $fillable = [
        'deploy_key',
        'mst_mailbox_id',
        'content_type',
        'content_id',
        'amount',
        'sort_desc',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deploy_key' => 'integer',
        'amount' => 'integer',
        'sort_desc' => 'integer',
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
        return ['mst_mailbox_id', 'content_type', 'content_id'];
    }

    /**
     * メールボックスとのリレーション
     *
     * @return BelongsTo
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(MstMailbox::class, 'mst_mailbox_id', 'id');
    }

    /**
     * コンテンツタイプを取得
     *
     * @return string
     */
    public function getContentType(): string
    {
        return $this->getAttribute('content_type');
    }

    /**
     * コンテンツIDを取得
     *
     * @return string
     */
    public function getContentId(): string
    {
        return $this->getAttribute('content_id');
    }

    /**
     * 数量を取得
     *
     * @return int
     */
    public function getAmount(): int
    {
        return $this->getAttribute('amount');
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
