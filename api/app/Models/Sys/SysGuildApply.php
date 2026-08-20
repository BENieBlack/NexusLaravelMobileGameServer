<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SysGuildApply Model
 *
 * ギルド加入申請テーブル
 *
 * @property int $id
 * @property int $sys_player_id
 * @property int $sys_guild_id
 * @property string|null $message
 * @property string $created_at
 * @property string $updated_at
 */
class SysGuildApply extends _BaseSys
{
    /**
     * テーブル名
     */
    protected $table = 'sys_guild_apply';

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    /** @var list<string> */
    protected $fillable = [
        'sys_guild_id',
        'sys_player_id',
        'status',
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
     * ステータスを取得
     */
    public function getStatus(): string
    {
        return $this->getAttribute('status');
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
     * ステータスを設定
     */
    public function setStatus(string $status): void
    {
        $this->setAttribute('status', $status);
    }
}
