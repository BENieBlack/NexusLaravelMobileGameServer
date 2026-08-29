<?php

namespace App\Models\Mst;

use App\Domain\Mailbox\Constants\Category;
use App\Domain\Mailbox\Constants\Priority;
use App\Domain\Mailbox\Constants\SenderType;
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
 * @property string|null $sender_id sender_typeで参照先が変わる多相参照（未使用）
 * @property int $expires_in_days
 * @property string|null $icon_url
 * @property bool $is_bulk_distributable
 * @property string $created_at
 * @property string $updated_at
 */
class MstMailbox extends _BaseMst
{
    public $table = 'mst_mailbox';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
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
    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'category' => Category::class,
        'priority' => Priority::class,
        'sender_type' => SenderType::class,
        'expires_in_days' => 'integer',
        'is_bulk_distributable' => 'boolean',
    ];

    public $timestamps = true;

    /**
     * メッセージとのリレーション
     */
    /**
     * @return BelongsTo<MstMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(MstMessage::class, 'mst_message_id', 'id');
    }

    /**
     * コンテンツとのリレーション
     */
    /**
     * @return HasMany<MstMailboxContent, $this>
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

        // toArray()の時点でEnumは値に落ちているので、Enum自体はモデルの属性から取り直す
        $category = $this->getAttribute('category');
        if ($category instanceof Category) {
            $array['category'] = $category->value;
            $array['category_label'] = $category->label();
            $array['category_icon'] = $category->icon();
        }

        $priority = $this->getAttribute('priority');
        if ($priority instanceof Priority) {
            $array['priority'] = $priority->value;
            $array['priority_label'] = $priority->label();
            $array['priority_color'] = $priority->color();
            $array['priority_icon'] = $priority->icon();
        }

        $senderType = $this->getAttribute('sender_type');
        if ($senderType instanceof SenderType) {
            $array['sender_type'] = $senderType->value;
            $array['sender_type_label'] = $senderType->label();
        }

        return $array;
    }
}
