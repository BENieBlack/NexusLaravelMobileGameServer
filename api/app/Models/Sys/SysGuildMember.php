<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexus\Core\Utilities\ClockUtility;

/**
 * SysGuildMember Model
 *
 * ギルドメンバーテーブル
 *
 * @property int $id
 * @property int $sys_guild_id
 * @property int $sys_player_id
 * @property string $role
 * @property int $contribution
 * @property string $joined_at
 * @property string $created_at
 * @property string $updated_at
 */
class SysGuildMember extends _BaseSys
{
    /**
     * テーブル名
     */
    protected $table = 'sys_guild_member';

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    /** @var list<string> */
    protected $fillable = [
        'sys_guild_id',
        'sys_player_id',
        'role',
        'joined_at',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected $casts = [
        'sys_guild_id' => 'integer',
        'sys_player_id' => 'integer',
    ];

    /**
     * ギルドとのリレーション
     */
    /**
     * @return BelongsTo<SysGuild, $this>
     */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(SysGuild::class, 'sys_guild_id');
    }

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
     * IDを取得
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * ギルドIDを取得
     */
    public function getSysGuildId(): int
    {
        return $this->getAttribute('sys_guild_id');
    }

    /**
     * プレイヤーIDを取得
     */
    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    /**
     * 役職を取得
     */
    public function getRole(): string
    {
        return $this->getAttribute('role');
    }

    /**
     * 加入日時を取得
     */
    public function getJoinedAt(): string
    {
        return ClockUtility::parse((string) $this->getAttribute('joined_at'))->format('Y-m-d H:i:s');
    }

    /**
     * ギルドIDを設定
     */
    public function setSysGuildId(int $guildId): void
    {
        $this->setAttribute('sys_guild_id', $guildId);
    }

    /**
     * プレイヤーIDを設定
     */
    public function setSysPlayerId(int $playerId): void
    {
        $this->setAttribute('sys_player_id', $playerId);
    }

    /**
     * 役職を設定
     */
    public function setRole(string $role): void
    {
        $this->setAttribute('role', $role);
    }

    /**
     * 加入日時を設定
     */
    public function setJoinedAt(string $joinedAt): void
    {
        $this->setAttribute('joined_at', $joinedAt);
    }
}
