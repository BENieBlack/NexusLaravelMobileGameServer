<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * MstLoginBonusContent Model
 * 
 * ログインボーナスの報酬内容
 * 
 * @property int $deploy_key
 * @property string $mst_login_bonus_id
 * @property string $content_type
 * @property string $content_id
 * @property int $amount
 * @property bool $is_paid
 * @property int $sort_order
 */
class MstLoginBonusContent extends _BaseMst
{
    public $table = 'mst_login_bonus_content';

    public $incrementing = false;
    
    /**
     * 複合主キー
     */
    protected $primaryKey = ['mst_login_bonus_id', 'content_type', 'content_id'];
    protected $keyType = 'string';

    /** @var array<int, string> */
    protected $fillable = [
        'deploy_key',
        'mst_login_bonus_id',
        'content_type',
        'content_id',
        'amount',
        'is_paid',
        'sort_order',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deploy_key' => 'integer',
        'amount' => 'integer',
        'is_paid' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;

    /**
     * 複合主キーを設定
     * 
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * 複合主キーの値を取得
     * 
     * @param  string|null  $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

    /**
     * ログインボーナス設定とのリレーション
     *
     * @return BelongsTo
     */
    public function loginBonus(): BelongsTo
    {
        return $this->belongsTo(MstLoginBonus::class, 'mst_login_bonus_id', 'id');
    }
}
