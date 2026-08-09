<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use NexusAuth\Contracts\DeviceModelInterface;
use NexusAuth\Contracts\PlayerModelInterface;

/**
 * SysPlayerDevice Model
 *
 * プレイヤーデバイス情報テーブル
 * デバイス固有IDとデバイス情報を管理
 */
class SysPlayerDevice extends _BaseSys implements DeviceModelInterface
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
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(SysPlayer::class, 'sys_player_id');
    }

    /**
     * トークン情報とのリレーション
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(SysPlayerToken::class, 'sys_player_device_id');
    }

    /**
     * device_id (uuid) からデバイスを取得
     */
    public static function findByDeviceId(string $deviceId): ?self
    {
        return static::where('uuid', $deviceId)->first();
    }

    /**
     * 最終ログイン日時を更新
     */
    public function updateLastLogin(): bool
    {
        $this->last_login_at = now();

        return $this->save();
    }

    /**
     * sys_player_idを取得
     */
    public function getSysPlayerId(): ?int
    {
        return $this->getAttribute('sys_player_id');
    }

    /**
     * sys_player_idを設定
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * uuidを取得 (内部用: nullable)
     */
    public function getUuidNullable(): ?string
    {
        return $this->getAttribute('uuid');
    }

    /**
     * uuidを取得 (NexusAuth DeviceModelInterface)
     */
    public function getUuid(): string
    {
        return $this->getAttribute('uuid') ?? '';
    }

    /**
     * uuidを設定
     */
    public function setUuid(string $uuid): void
    {
        $this->setAttribute('uuid', $uuid);
    }

    /**
     * device_infoを取得
     */
    public function getDeviceInfo(): ?array
    {
        return $this->getAttribute('device_info');
    }

    /**
     * device_infoを設定
     */
    public function setDeviceInfo(array $deviceInfo): void
    {
        $this->setAttribute('device_info', $deviceInfo);
    }

    /**
     * last_login_atをDateTimeオブジェクトで取得 (内部用)
     */
    public function getLastLoginAtDateTime(): ?\DateTime
    {
        return $this->getAttribute('last_login_at');
    }

    /**
     * last_login_atを文字列形式で取得 (NexusAuth DeviceModelInterface)
     */
    public function getLastLoginAt(): ?string
    {
        $lastLoginAt = $this->getAttribute('last_login_at');

        return $lastLoginAt ? $lastLoginAt->format('Y-m-d H:i:s') : null;
    }

    /**
     * last_login_atを設定
     */
    public function setLastLoginAt(\DateTime|string $lastLoginAt): void
    {
        $this->setAttribute('last_login_at', $lastLoginAt);
    }

    /**
     * レスポンス用配列に変換
     *
     * データベース層の'id'をAPI層の'sys_player_device_id'に変換
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

    // ========================================
    // NexusAuth DeviceModelInterface の実装
    // ========================================

    /**
     * IDを取得 (NexusAuth DeviceModelInterface)
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * プレイヤーIDを取得 (NexusAuth DeviceModelInterface)
     */
    public function getPlayerId(): int
    {
        return $this->sys_player_id;
    }

    /**
     * プレイヤーを取得 (NexusAuth DeviceModelInterface)
     */
    public function getPlayer(): ?PlayerModelInterface
    {
        return $this->player;
    }
}
