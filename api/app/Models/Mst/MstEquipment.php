<?php

namespace App\Models\Mst;

/**
 * MstEquipment Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $type
 * @property string $element
 * @property string $rarity
 * @property int $attack
 * @property int $defense
 * @property int $hp
 * @property int $sort_desc
 * @property bool $is_active
 */
class MstEquipment extends _BaseMst
{
    public $table = 'mst_equipment';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'type',
        'element',
        'rarity',
        'attack',
        'defense',
        'hp',
        'sort_desc',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
        'attack' => 'integer',
        'defense' => 'integer',
        'hp' => 'integer',
        'sort_desc' => 'integer',
        'is_active' => 'boolean',
    ];

    public $timestamps = true;

    /**
     * レスポンス用配列に変換
     *
     * Note: 主キーが文字列型（semantic ID like "equipment_sword_001"）のため、変換不要
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }

    // ===== Getter Methods =====

    /**
     * レアリティを取得
     */
    public function getRarity(): string
    {
        return $this->rarity;
    }
}
