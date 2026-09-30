<?php

namespace App\Models\Mst;

/**
 * @property string $rarity
 */
class MstUnit extends _BaseMst
{
    public $table = 'mst_unit';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'type',
        'element',
        'rarity',
    ];

    /**
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected $casts = [
        'deploy_key' => 'integer',
    ];

    public $timestamps = true;

    /**
     * レスポンス用配列に変換
     *
     * Note: 主キーが文字列型（semantic ID like "unit_001"）のため、変換不要
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
