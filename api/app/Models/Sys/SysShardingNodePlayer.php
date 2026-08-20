<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SysShardingNodePlayer Model
 *
 * プレイヤーのシャーディングノード割り当てを管理するモデル
 */
class SysShardingNodePlayer extends _BaseSys
{
    /**
     * テーブル名
     */
    protected $table = 'sys_sharding_node_player';

    /**
     * プライマリキー
     */
    protected $primaryKey = 'sys_player_id';

    /**
     * 自動インクリメント無効化
     */
    public $incrementing = false;

    /**
     * プライマリキーの型
     */
    protected $keyType = 'int';

    /**
     * 複数代入可能な属性
     *
     * @var array
     */
    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'sys_sharding_node_id',
        'assigned_at',
    ];

    /**
     * キャストする属性
     *
     * @var array
     */
    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'sys_sharding_node_id' => 'integer',
    ];

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
     * sys_sharding_node_idを取得
     */
    public function getSysShardingNodeId(): ?int
    {
        return $this->getAttribute('sys_sharding_node_id');
    }

    /**
     * sys_sharding_node_idを設定
     */
    public function setSysShardingNodeId(int $sysShardingNodeId): void
    {
        $this->setAttribute('sys_sharding_node_id', $sysShardingNodeId);
    }

    /**
     * assigned_atを取得
     */
    public function getAssignedAt(): ?string
    {
        return $this->getAttribute('assigned_at');
    }

    /**
     * assigned_atを設定
     */
    public function setAssignedAt(\DateTime|string $assignedAt): void
    {
        $this->setAttribute('assigned_at', $assignedAt);
    }

    /**
     * シャーディングノードとのリレーション
     */
    /**
     * @return BelongsTo<SysShardingNode, $this>
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(SysShardingNode::class, 'sys_sharding_node_id');
    }

    /**
     * sys_playerとのリレーション
     */
    /**
     * @return BelongsTo<SysPlayer, $this>
     */
    public function sysPlayer(): BelongsTo
    {
        return $this->belongsTo(SysPlayer::class, 'sys_player_id');
    }

    /**
     * プレイヤーIDからノード割り当てを検索
     */
    public static function findBySysPlayerId(int $sysPlayerId): ?self
    {
        return self::find($sysPlayerId);
    }

    /**
     * プレイヤーIDが割り当て済みかチェック
     */
    public static function isPlayerAssigned(int $sysPlayerId): bool
    {
        return self::where('sys_player_id', $sysPlayerId)->exists();
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 主キーがsys_player_idであり、idフィールドは存在しない
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
