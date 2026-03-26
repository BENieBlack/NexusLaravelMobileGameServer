<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SysPlayerDevice Model
 * 
 * プレイヤーデバイス情報テーブル
 * デバイス固有IDとデバイス情報を管理
 */
class SysPlayerDevice extends _BaseSys
{

    /**
     * テーブル名
     */
    protected $table = 'sys_player_device';

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'sys_player_id',
        'uuid',
        'device_info',
        'last_login_at',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'device_info' => 'array',
        'last_login_at' => 'datetime',
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
     * トークン情報とのリレーション
     *
     * @return HasMany
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(SysPlayerToken::class, 'sys_player_device_id');
    }

    /**
     * device_id (uuid) からデバイスを取得
     *
     * @param string $deviceId
     * @return self|null
     */
    public static function findByDeviceId(string $deviceId): ?self
    {
        return static::where('uuid', $deviceId)->first();
    }

    /**
     * 最終ログイン日時を更新
     *
     * @return bool
     */
    public function updateLastLogin(): bool
    {
        $this->last_login_at = now();
        return $this->save();
    }

    /**
     * sys_player_idを取得
     *
     * @return int|null
     */
    public function getSysPlayerId(): ?int
    {
        return $this->getAttribute('sys_player_id');
    }

    /**
     * sys_player_idを設定
     *
     * @param int $sysPlayerId
     * @return void
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * uuidを取得
     *
     * @return string|null
     */
    public function getUuid(): ?string
    {
        return $this->getAttribute('uuid');
    }

    /**
     * uuidを設定
     *
     * @param string $uuid
     * @return void
     */
    public function setUuid(string $uuid): void
    {
        $this->setAttribute('uuid', $uuid);
    }

    /**
     * device_infoを取得
     *
     * @return array|null
     */
    public function getDeviceInfo(): ?array
    {
        return $this->getAttribute('device_info');
    }

    /**
     * device_infoを設定
     *
     * @param array $deviceInfo
     * @return void
     */
    public function setDeviceInfo(array $deviceInfo): void
    {
        $this->setAttribute('device_info', $deviceInfo);
    }

    /**
     * last_login_atを取得
     *
     * @return \DateTime|null
     */
    public function getLastLoginAt(): ?\DateTime
    {
        return $this->getAttribute('last_login_at');
    }

    /**
     * last_login_atを設定
     *
     * @param \DateTime|string $lastLoginAt
     * @return void
     */
    public function setLastLoginAt(\DateTime|string $lastLoginAt): void
    {
        $this->setAttribute('last_login_at', $lastLoginAt);
    }

    /**
     * レスポンス用配列に変換
     * 
     * データベース層の'id'をAPI層の'sys_player_device_id'に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();
        
        if (isset($array['id'])) {
            $array['sys_player_device_id'] = $array['id'];
            unset($array['id']);
        }
        
        return $array;
    }
}
