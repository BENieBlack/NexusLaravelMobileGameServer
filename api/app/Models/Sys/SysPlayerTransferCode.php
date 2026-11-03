<?php

namespace App\Models\Sys;

/**
 * SysPlayerTransferCode Model
 *
 * 引き継ぎコード。プレイヤーごとに1行で、発行し直すと上書きする。
 * 引き継ぎに成功したら削除する（同じコードは2度使えない）。
 *
 * @property int $id
 * @property int $sys_player_id
 * @property string $transfer_code
 * @property string $password_hash
 * @property int $failed_count
 * @property ?string $locked_until
 * @property string $expires_at
 * @property string $created_at
 * @property string $updated_at
 */
class SysPlayerTransferCode extends _BaseSys
{
    protected $table = 'sys_player_transfer_code';

    /** @var list<string> */
    protected array $selectKeys = ['sys_player_id'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'transfer_code',
        'password_hash',
        'failed_count',
        'locked_until',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'failed_count' => 'integer',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password_hash',
    ];

    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    public function getTransferCode(): string
    {
        return $this->getAttribute('transfer_code');
    }

    public function setTransferCode(string $transferCode): void
    {
        $this->setAttribute('transfer_code', $transferCode);
    }

    public function getPasswordHash(): string
    {
        return $this->getAttribute('password_hash');
    }

    public function setPasswordHash(string $passwordHash): void
    {
        $this->setAttribute('password_hash', $passwordHash);
    }

    public function getFailedCount(): int
    {
        return (int) $this->getAttribute('failed_count');
    }

    public function setFailedCount(int $failedCount): void
    {
        $this->setAttribute('failed_count', $failedCount);
    }

    public function getLockedUntil(): ?string
    {
        return $this->getDateAttributeString('locked_until');
    }

    public function setLockedUntil(?string $lockedUntil): void
    {
        $this->setAttribute('locked_until', $lockedUntil);
    }

    public function getExpiresAt(): string
    {
        return (string) $this->getDateAttributeString('expires_at');
    }

    public function setExpiresAt(string $expiresAt): void
    {
        $this->setAttribute('expires_at', $expiresAt);
    }
}
