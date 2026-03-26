<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SysPlayerToken Model
 * 
 * プレイヤートークン管理テーブル
 * リフレッシュトークンのハッシュと有効期限を管理
 * 
 * @property int $id
 * @property int $sys_player_id
 * @property int $sys_player_device_id
 * @property string $refresh_token_hash
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property-read SysPlayer $player
 * @property-read SysPlayerDevice $device
 */
class SysPlayerToken extends _BaseSys
{

    /**
     * テーブル名
     */
    protected $table = 'sys_player_token';

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'sys_player_id',
        'sys_player_device_id',
        'refresh_token_hash',
        'expires_at',
        'revoked_at',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * プレイヤーとのリレーション
     *
     * @return BelongsTo
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(SysPlayer::class, 'sys_player_id');
    }

    /**
     * デバイスとのリレーション
     *
     * @return BelongsTo
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(SysPlayerDevice::class, 'sys_player_device_id');
    }

    /**
     * IDを取得
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * プレイヤーIDを取得
     *
     * @return int
     */
    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    /**
     * プレイヤーデバイスIDを取得
     *
     * @return int
     */
    public function getSysPlayerDeviceId(): int
    {
        return $this->getAttribute('sys_player_device_id');
    }

    /**
     * リフレッシュトークンハッシュを取得
     *
     * @return string
     */
    public function getRefreshTokenHash(): string
    {
        return $this->getAttribute('refresh_token_hash');
    }

    /**
     * 有効期限を取得
     *
     * @return \Illuminate\Support\Carbon
     */
    public function getExpiresAt(): \Illuminate\Support\Carbon
    {
        return $this->getAttribute('expires_at');
    }

    /**
     * 無効化日時を取得
     *
     * @return \Illuminate\Support\Carbon|null
     */
    public function getRevokedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->getAttribute('revoked_at');
    }

    /**
     * プレイヤーIDを設定
     *
     * @param int $sysPlayerId
     * @return void
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * プレイヤーデバイスIDを設定
     *
     * @param int $sysPlayerDeviceId
     * @return void
     */
    public function setSysPlayerDeviceId(int $sysPlayerDeviceId): void
    {
        $this->setAttribute('sys_player_device_id', $sysPlayerDeviceId);
    }

    /**
     * リフレッシュトークンハッシュを設定
     *
     * @param string $refreshTokenHash
     * @return void
     */
    public function setRefreshTokenHash(string $refreshTokenHash): void
    {
        $this->setAttribute('refresh_token_hash', $refreshTokenHash);
    }

    /**
     * 有効期限を設定
     *
     * @param \Illuminate\Support\Carbon $expiresAt
     * @return void
     */
    public function setExpiresAt(\Illuminate\Support\Carbon $expiresAt): void
    {
        $this->setAttribute('expires_at', $expiresAt);
    }

    /**
     * 無効化日時を設定
     *
     * @param \Illuminate\Support\Carbon|null $revokedAt
     * @return void
     */
    public function setRevokedAt(?\Illuminate\Support\Carbon $revokedAt): void
    {
        $this->setAttribute('revoked_at', $revokedAt);
    }

    /**
     * トークンが有効かチェック
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->getRevokedAt() === null 
            && $this->getExpiresAt() > now();
    }

    /**
     * トークンが期限切れかチェック
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->getExpiresAt() <= now();
    }

    /**
     * トークンを無効化
     *
     * @return bool
     */
    public function revoke(): bool
    {
        $this->revoked_at = now();
        return $this->save();
    }

    /**
     * refresh_token_hashから有効なトークンを取得
     *
     * @param string $tokenHash
     * @return self|null
     */
    public static function findValidByHash(string $tokenHash): ?self
    {
        return static::where('refresh_token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * 有効なトークンのスコープ
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        return $query->whereNull('revoked_at')
                    ->where('expires_at', '>', now());
    }

    /**
     * レスポンス用配列に変換
     * 
     * データベース層の'id'をAPI層の'sys_player_token_id'に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();
        
        if (isset($array['id'])) {
            $array['sys_player_token_id'] = $array['id'];
            unset($array['id']);
        }
        
        return $array;
    }
}
