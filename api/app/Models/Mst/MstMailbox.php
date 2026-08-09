<?php

namespace App\Models\Mst;

use App\Domain\MailBox\Constants\Category;
use App\Domain\MailBox\Constants\Priority;
use App\Domain\MailBox\Constants\SenderType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MstMailbox Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_message_id
 * @property Category $category
 * @property Priority $priority
 * @property SenderType $sender_type
 * @property string|null $sender_id
 * @property int $expires_in_days
 * @property string|null $icon_url
 * @property bool $is_bulk_distributable
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
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
        'category',
        'priority',
        'sender_type',
        'sender_id',
        'expires_in_days',
        'icon_url',
        'is_bulk_distributable',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deploy_key' => 'integer',
        'category' => Category::class,
        'priority' => Priority::class,
        'sender_type' => SenderType::class,
        'expires_in_days' => 'integer',
        'is_bulk_distributable' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;

    /**
     * メッセージとのリレーション
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(MstMessage::class, 'mst_message_id', 'id');
    }

    /**
     * コンテンツとのリレーション
     */
    public function contentCollection(): HasMany
    {
        return $this->hasMany(MstMailboxContent::class, 'mst_mailbox_id', 'id');
    }

    /**
     * カテゴリを取得
     */
    public function getCategory(): Category
    {
        return $this->getAttribute('category');
    }

    /**
     * 優先度を取得
     */
    public function getPriority(): Priority
    {
        return $this->getAttribute('priority');
    }

    /**
     * 送信者タイプを取得
     */
    public function getSenderType(): SenderType
    {
        return $this->getAttribute('sender_type');
    }

    /**
     * 有効期限（日数）を取得
     */
    public function getExpiresInDays(): int
    {
        return $this->getAttribute('expires_in_days');
    }

    /**
     * レスポンス用配列に変換
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        // Enumを文字列に変換
        if (isset($array['category']) && $array['category'] instanceof Category) {
            $array['category'] = $array['category']->value;
            $array['category_label'] = $array['category']->label();
            $array['category_icon'] = $array['category']->icon();
        }

        if (isset($array['priority']) && $array['priority'] instanceof Priority) {
            $array['priority'] = $array['priority']->value;
            $array['priority_label'] = $array['priority']->label();
            $array['priority_color'] = $array['priority']->color();
            $array['priority_icon'] = $array['priority']->icon();
        }

        if (isset($array['sender_type']) && $array['sender_type'] instanceof SenderType) {
            $array['sender_type'] = $array['sender_type']->value;
            $array['sender_type_label'] = $array['sender_type']->label();
        }

        return $array;
    }
}
