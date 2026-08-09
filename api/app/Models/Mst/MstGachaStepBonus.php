<?php

namespace App\Models\Mst;

/**
 * MstGachaStepBonus Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $mst_gacha_step_id
 * @property int $position
 * @property int $bonus_count
 * @property string $selection_type
 * @property int|null $bonus_rarity
 * @property string|null $bonus_content_type
 * @property bool $is_pickup_only
 * @property bool $is_active
 */
class MstGachaStepBonus extends _BaseMst
{
    public $table = 'mst_gacha_step_bonus';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'deploy_key',
        'id',
        'mst_gacha_step_id',
        'position',
        'bonus_count',
        'selection_type',
        'bonus_rarity',
        'bonus_content_type',
        'is_pickup_only',
        'is_active',
    ];

    protected $casts = [
        'deploy_key' => 'integer',
        'position' => 'integer',
        'bonus_count' => 'integer',
        'bonus_rarity' => 'integer',
        'is_pickup_only' => 'boolean',
        'is_active' => 'boolean',
    ];

    public $timestamps = true;

    /**
     * ボーナス数量を取得
     */
    public function getBonusCount(): int
    {
        return $this->getAttribute('bonus_count');
    }

    /**
     * 選択タイプを取得
     *
     * @return string none, random, choice
     */
    public function getSelectionType(): string
    {
        return $this->getAttribute('selection_type');
    }

    /**
     * ボーナスレアリティを取得
     */
    public function getBonusRarity(): ?int
    {
        return $this->getAttribute('bonus_rarity');
    }

    /**
     * ボーナスコンテンツタイプを取得
     */
    public function getBonusContentType(): ?string
    {
        return $this->getAttribute('bonus_content_type');
    }

    /**
     * ピックアップ限定かを取得
     */
    public function isPickupOnly(): bool
    {
        return $this->getAttribute('is_pickup_only');
    }

    /**
     * 有効フラグを取得
     */
    public function isActive(): bool
    {
        return $this->getAttribute('is_active');
    }
}
