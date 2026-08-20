<?php

namespace App\Models\Trx;

use App\Traits\CompositePrimaryKeyTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxPlayerSns Model
 *
 * プレイヤーのSNS連携情報を管理するモデル
 * PRIMARY KEY: (sys_player_id, sns_type)
 */
class TrxPlayerSns extends _BaseTrx
{
    use CompositePrimaryKeyTrait;

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

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'sns_type',
        'sns_user_id',
        'auth',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
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
     */
    public static function availableTypes(): array
    {
        return [
            self::TYPE_APPLE,
            self::TYPE_GOOGLE,
            self::TYPE_X,
            self::TYPE_FACEBOOK,
        ];
    }

    /**
     * trx_playerとのリレーション
     */
    /**
     * @return BelongsTo<TrxPlayer, $this>
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * システムプレイヤーIDを設定
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * SNSタイプを設定
     */
    public function setSnsType(string $snsType): void
    {
        $this->setAttribute('sns_type', $snsType);
    }

    /**
     * SNSユーザーIDを設定
     */
    public function setSnsUserId(string $snsUserId): void
    {
        $this->setAttribute('sns_user_id', $snsUserId);
    }

    /**
     * 認証情報を設定
     */
    public function setAuth(string $auth): void
    {
        $this->setAttribute('auth', $auth);
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 複合主キー(sys_player_id, sns_type)のため、idフィールドは存在しない
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
