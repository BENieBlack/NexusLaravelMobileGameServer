<?php

namespace App\Models\Mst;

/**
 * MstGachaStepBonusContent Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_gacha_step_bonus_id
 * @property string $content_type
 * @property string $content_id
 * @property array|null $content_option
 * @property int $content_quantity
 * @property int $amount
 * @property int $weight
 * @property int $sort_order
 * @property bool $is_active
 */
class MstGachaStepBonusContent extends _BaseMst
{
    public $table = 'mst_gacha_step_bonus_content';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'mst_gacha_step_bonus_id',
        'content_type',
        'content_id',
        'content_option',
        'content_quantity',
        'amount',
        'weight',
        'sort_order',
        'is_active',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'content_option' => 'array',
        'content_quantity' => 'integer',
        'amount' => 'integer',
        'weight' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public $timestamps = true;

    /**
     * ステップボーナスIDを取得
     */
    public function getMstGachaStepBonusId(): string
    {
        return $this->getAttribute('mst_gacha_step_bonus_id');
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
     * 重みを取得（randomの場合の抽選確率）
     */
    public function getWeight(): int
    {
        return $this->getAttribute('weight');
    }

    /**
     * 表示順序を取得（choiceの場合）
     */
    public function getSortOrder(): int
    {
        return $this->getAttribute('sort_order');
    }

    /**
     * 有効フラグを取得
     */
    public function isActive(): bool
    {
        return $this->getAttribute('is_active');
    }
}
