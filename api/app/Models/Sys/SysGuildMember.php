<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SysGuildMember Model
 * 
 * ギルドメンバーテーブル
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
    protected $casts = [
        'sys_guild_id' => 'integer',
        'sys_player_id' => 'integer',
        'joined_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * ギルドとのリレーション
     *
     * @return BelongsTo
     */
    public function guild(): BelongsTo
    {
        return $this->belongsTo(SysGuild::class, 'sys_guild_id');
    }

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
     * IDを取得
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * ギルドIDを取得
     *
     * @return int
     */
    public function getSysGuildId(): int
    {
        return $this->getAttribute('sys_guild_id');
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
     * 役職を取得
     *
     * @return string
     */
    public function getRole(): string
    {
        return $this->getAttribute('role');
    }

    /**
     * 加入日時を取得
     *
     * @return string
     */
    public function getJoinedAt(): string
    {
        return $this->getAttribute('joined_at')->format('Y-m-d H:i:s');
    }

    /**
     * ギルドIDを設定
     *
     * @param int $guildId
     * @return void
     */
    public function setSysGuildId(int $guildId): void
    {
        $this->setAttribute('sys_guild_id', $guildId);
    }

    /**
     * プレイヤーIDを設定
     *
     * @param int $playerId
     * @return void
     */
    public function setSysPlayerId(int $playerId): void
    {
        $this->setAttribute('sys_player_id', $playerId);
    }

    /**
     * 役職を設定
     *
     * @param string $role
     * @return void
     */
    public function setRole(string $role): void
    {
        $this->setAttribute('role', $role);
    }

    /**
     * 加入日時を設定
     *
     * @param string $joinedAt
     * @return void
     */
    public function setJoinedAt(string $joinedAt): void
    {
        $this->setAttribute('joined_at', $joinedAt);
    }
}
