<?php

namespace App\Models\Mst;

/**
 * MstItem Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $type
 * @property string $effect
 * @property int $value
 */
class MstItem extends _BaseMst
{
    public $table = 'mst_item';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var array<int, string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'type',
        'effect',
        'value',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deploy_key' => 'integer',
        'value' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;

    /**
     * アイテムタイプを取得
     */
    public function getType(): string
    {
        return $this->getAttribute('type');
    }

    /**
     * アイテム効果を取得
     */
    public function getEffect(): string
    {
        return $this->getAttribute('effect');
    }

    /**
     * アイテム値を取得
     */
    public function getValue(): int
    {
        return $this->getAttribute('value');
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 主キーが文字列型（semantic ID like "item_gold", "item_exp_potion"）のため、変換不要
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
