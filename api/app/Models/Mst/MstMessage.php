<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MstMessage Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $created_at
 * @property string $updated_at
 */
class MstMessage extends _BaseMst
{
    public $table = 'mst_message';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'deploy_key',
        'id',
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
     * 多言語データとのリレーション
     */
    /**
     * @return HasMany<MstMessageI18n, $this>
     */
    public function i18n(): HasMany
    {
        return $this->hasMany(MstMessageI18n::class, 'mst_message_id', 'id');
    }

    /**
     * レスポンス用配列に変換
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
