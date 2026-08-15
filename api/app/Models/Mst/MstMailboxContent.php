<?php

namespace App\Models\Mst;

use Carbon\CarbonImmutable;
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
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class MstMailboxContent extends _BaseMst
{
    public $table = 'mst_mailbox_content';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_mailbox_id',
        'content_type',
        'content_id',
        'content_option',
        'content_quantity',
        'amount',
        'sort_desc',
    ];

    /**
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'content_option' => 'array',
        'content_quantity' => 'integer',
        'amount' => 'integer',
        'sort_desc' => 'integer',
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
     */
    /**
     * @return BelongsTo<MstMailbox, $this>
     */
    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(MstMailbox::class, 'mst_mailbox_id', 'id');
    }

    /**
     * コンテンツタイプを取得
     */
    public function getContentType(): string
    {
        return $this->getAttribute('content_type');
    }

    /**
     * コンテンツIDを取得
     */
    public function getContentId(): string
    {
        return $this->getAttribute('content_id');
    }

    /**
     * コンテンツオプションを取得
     */
    public function getContentOption(): ?array
    {
        return $this->getAttribute('content_option');
    }

    /**
     * コンテンツ数量を取得（1配布あたり）
     */
    public function getContentQuantity(): int
    {
        return $this->getAttribute('content_quantity');
    }

    /**
     * 数量を取得（配布回数）
     */
    public function getAmount(): int
    {
        return $this->getAttribute('amount');
    }

    /**
     * 実際の配布総量を取得（content_quantity × amount）
     */
    public function getTotalQuantity(): int
    {
        return $this->getContentQuantity() * $this->getAmount();
    }

    /**
     * レスポンス用配列に変換
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
