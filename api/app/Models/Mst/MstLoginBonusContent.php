<?php

namespace App\Models\Mst;

use App\Traits\CompositePrimaryKeyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MstLoginBonusContent Model
 *
 * ログインボーナスの報酬内容
 *
 * @property int $deploy_key
 * @property string $mst_login_bonus_id
 * @property string $content_type
 * @property string $content_mst_id
 * @property int $amount
 * @property bool $is_paid
 * @property int $sort_order
 */
class MstLoginBonusContent extends _BaseMst
{
    use CompositePrimaryKeyTrait;

    public $table = 'mst_login_bonus_content';

    public $incrementing = false;

    /**
     * 複合主キー
     */
    protected $primaryKey = ['mst_login_bonus_id', 'content_type', 'content_mst_id'];

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'mst_login_bonus_id',
        'content_type',
        'content_mst_id',
        'content_option',
        'content_quantity',
        'amount',
        'is_paid',
        'sort_order',
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
        'is_paid' => 'boolean',
        'sort_order' => 'integer',
    ];

    public $timestamps = true;

    /**
     * ログインボーナス設定とのリレーション
     */
    /**
     * @return BelongsTo<MstLoginBonus, $this>
     */
    public function loginBonus(): BelongsTo
    {
        return $this->belongsTo(MstLoginBonus::class, 'mst_login_bonus_id', 'id');
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
    public function getContentMstId(): string
    {
        return $this->getAttribute('content_mst_id');
    }

    /**
     * コンテンツオプションを取得
     *
     * @return array<string, mixed>|null
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
     * 配布回数を取得
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
     * 有償フラグを取得
     */
    public function getIsPaid(): bool
    {
        return $this->getAttribute('is_paid');
    }
}
