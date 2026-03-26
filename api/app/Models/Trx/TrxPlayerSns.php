<?php

namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxPlayerSns Model
 * 
 * プレイヤーのSNS連携情報を管理するモデル
 * PRIMARY KEY: (sys_player_id, sns_type)
 */
class TrxPlayerSns extends _BaseTrx
{
    protected $table = 'trx_player_sns';

    /**
     * 複合主キーのため、auto incrementを無効化
     */
    public $incrementing = false;

    /**
     * 複合主キーの指定
     */
    protected $primaryKey = ['sys_player_id', 'sns_type'];

    /**
     * 主キーの型
     */
    protected $keyType = 'string';

    protected $fillable = [
        'sys_player_id',
        'sns_type',
        'sns_user_id',
        'auth',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'sys_player_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * SNSタイプの定数
     */
    public const TYPE_APPLE = 'apple';
    public const TYPE_GOOGLE = 'google';
    public const TYPE_X = 'x';
    public const TYPE_FACEBOOK = 'facebook';

    /**
     * 利用可能なSNSタイプ一覧を取得
     *
     * @return array
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_APPLE,
            self::TYPE_GOOGLE,
            self::TYPE_X,
            self::TYPE_FACEBOOK,
        ];
    }

    /**
     * 複合主キーを設定
     * 
     * @param  array  $ids
     * @return $this
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
     * @param  string  $keyName
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
     * trx_playerとのリレーション
     *
     * @return BelongsTo
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * システムプレイヤーIDを設定
     *
     * @param int $sysPlayerId
     * @return void
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * SNSタイプを設定
     *
     * @param string $snsType
     * @return void
     */
    public function setSnsType(string $snsType): void
    {
        $this->setAttribute('sns_type', $snsType);
    }

    /**
     * SNSユーザーIDを設定
     *
     * @param string $snsUserId
     * @return void
     */
    public function setSnsUserId(string $snsUserId): void
    {
        $this->setAttribute('sns_user_id', $snsUserId);
    }

    /**
     * 認証情報を設定
     *
     * @param string $auth
     * @return void
     */
    public function setAuth(string $auth): void
    {
        $this->setAttribute('auth', $auth);
    }

    /**
     * レスポンス用配列に変換
     * 
     * Note: 複合主キー(sys_player_id, sns_type)のため、idフィールドは存在しない
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
