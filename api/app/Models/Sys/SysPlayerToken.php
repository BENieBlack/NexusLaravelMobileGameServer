<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexus\Core\Utilities\ClockUtility;
use NexusAuth\Contracts\TokenModelInterface;

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
 * @property string $expires_at
 * @property ?string $revoked_at
 * @property string $created_at
 * @property string $updated_at
 * @property-read SysPlayer $player
 * @property-read SysPlayerDevice $device
 */
class SysPlayerToken extends _BaseSys implements TokenModelInterface
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
    /** @var list<string> */
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
    /** @var array<string, string> */
    protected $casts = [
    ];

    /**
     * プレイヤーとのリレーション
     */
    /**
     * @return BelongsTo<SysPlayer, $this>
     */
    public function player(): BelongsTo
    {
        return $this->belongsTo(SysPlayer::class, 'sys_player_id');
    }

    /**
     * デバイスとのリレーション
     */
    /**
     * @return BelongsTo<SysPlayerDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(SysPlayerDevice::class, 'sys_player_device_id');
    }

    /**
     * IDを取得
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * プレイヤーIDを取得
     */
    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    /**
     * プレイヤーデバイスIDを取得
     */
    public function getSysPlayerDeviceId(): int
    {
        return $this->getAttribute('sys_player_device_id');
    }

    /**
     * リフレッシュトークンハッシュを取得
     */
    public function getRefreshTokenHash(): string
    {
        return $this->getAttribute('refresh_token_hash');
    }

    /**
     * 無効化日時を取得
     */
    public function getRevokedAt(): ?string
    {
        return $this->getDateAttributeString('revoked_at');
    }

    /**
     * プレイヤーIDを設定
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * プレイヤーデバイスIDを設定
     */
    public function setSysPlayerDeviceId(int $sysPlayerDeviceId): void
    {
        $this->setAttribute('sys_player_device_id', $sysPlayerDeviceId);
    }

    /**
     * リフレッシュトークンハッシュを設定
     */
    public function setRefreshTokenHash(string $refreshTokenHash): void
    {
        $this->setAttribute('refresh_token_hash', $refreshTokenHash);
    }

    /**
     * 有効期限を設定
     */
    public function setExpiresAt(\DateTimeInterface|string $expiresAt): void
    {
        $this->setAttribute('expires_at', $expiresAt);
    }

    /**
     * 無効化日時を設定
     */
    public function setRevokedAt(\DateTimeInterface|string|null $revokedAt): void
    {
        $this->setAttribute('revoked_at', $revokedAt);
    }

    /**
     * トークンが有効かチェック
     */
    public function isValid(): bool
    {
        return $this->getRevokedAt() === null
            && ClockUtility::isFuture($this->getExpiresAt());
    }

    /**
     * トークンが期限切れかチェック
     */
    public function isExpired(): bool
    {
        return ! ClockUtility::isFuture($this->getExpiresAt());
    }

    /**
     * トークンを無効化
     *
     * revoked_atを設定するだけでDBには反映しない。
     * 永続化はRepositoryのsetModel()経由で行うこと。
     */
    public function revoke(): void
    {
        $this->revoked_at = now();
    }

    /**
     * refresh_token_hashから有効なトークンを取得
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
     * @param  Builder  $query
     * @return Builder
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

    // ========================================
    // NexusAuth TokenModelInterface の実装
    // ========================================

    /**
     * プレイヤーIDを取得 (NexusAuth TokenModelInterface)
     */
    public function getPlayerId(): int
    {
        return $this->sys_player_id;
    }

    /**
     * リフレッシュトークンを取得 (NexusAuth TokenModelInterface)
     */
    public function getRefreshToken(): string
    {
        return $this->refresh_token_hash;
    }

    /**
     * 有効期限を文字列形式で取得 (NexusAuth TokenModelInterface)
     */
    public function getExpiresAt(): string
    {
        return (string) $this->getDateAttributeString('expires_at');
    }
}
