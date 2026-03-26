<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MstMessage Model
 * 
 * @property int $deploy_key
 * @property string $id
 * @property \Carbon\CarbonImmutable $created_at
 * @property \Carbon\CarbonImmutable $updated_at
 */
class MstMessage extends _BaseMst
{
    public $table = 'mst_message';

    public $incrementing = false;
    protected $keyType = 'string';

    /** @var array<int, string> */
    protected $fillable = [
        'deploy_key',
        'id',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deploy_key' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;

    /**
     * 多言語データとのリレーション
     *
     * @return HasMany
     */
    public function i18n(): HasMany
    {
        return $this->hasMany(MstMessageI18n::class, 'mst_message_id', 'id');
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
