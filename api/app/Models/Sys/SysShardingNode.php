<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SysShardingNode Model
 *
 * シャーディングノード（各データベースインスタンス）を管理するモデル
 */
class SysShardingNode extends _BaseSys
{
    /**
     * テーブル名
     */
    protected $table = 'sys_sharding_node';

    /**
     * 複数代入可能な属性
     *
     * @var array
     */
    protected $fillable = [
        'sys_sharding_id',
        'node_name',
        'node_no',
        'weight',
        'status',
        'is_writable',
        'is_readable',
        'max_connections',
        'current_player_count',
    ];

    /**
     * キャストする属性
     *
     * @var array
     */
    protected $casts = [
        'sys_sharding_id' => 'integer',
        'node_no' => 'integer',
        'weight' => 'integer',
        'is_writable' => 'boolean',
        'is_readable' => 'boolean',
        'max_connections' => 'integer',
        'current_player_count' => 'integer',
    ];

    /**
     * ノードステータスの定数
     */
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_MAINTENANCE = 'maintenance';

    /**
     * 利用可能なステータス一覧を取得
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_INACTIVE,
            self::STATUS_MAINTENANCE,
        ];
    }

    /**
     * sys_sharding_idを取得
     */
    public function getSysShardingId(): ?int
    {
        return $this->getAttribute('sys_sharding_id');
    }

    /**
     * sys_sharding_idを設定
     */
    public function setSysShardingId(int $sysShardingId): void
    {
        $this->setAttribute('sys_sharding_id', $sysShardingId);
    }

    /**
     * node_nameを取得
     */
    public function getNodeName(): ?string
    {
        return $this->getAttribute('node_name');
    }

    /**
     * node_nameを設定
     */
    public function setNodeName(string $nodeName): void
    {
        $this->setAttribute('node_name', $nodeName);
    }

    /**
     * node_noを取得
     */
    public function getNodeNo(): ?int
    {
        return $this->getAttribute('node_no');
    }

    /**
     * node_noを設定
     */
    public function setNodeNo(int $nodeNo): void
    {
        $this->setAttribute('node_no', $nodeNo);
    }

    /**
     * weightを取得
     */
    public function getWeight(): ?int
    {
        return $this->getAttribute('weight');
    }

    /**
     * weightを設定
     */
    public function setWeight(int $weight): void
    {
        $this->setAttribute('weight', $weight);
    }

    /**
     * statusを取得
     */
    public function getStatus(): ?string
    {
        return $this->getAttribute('status');
    }

    /**
     * statusを設定
     */
    public function setStatus(string $status): void
    {
        $this->setAttribute('status', $status);
    }

    /**
     * is_writableを設定
     */
    public function setIsWritable(bool $isWritable): void
    {
        $this->setAttribute('is_writable', $isWritable);
    }

    /**
     * is_readableを設定
     */
    public function setIsReadable(bool $isReadable): void
    {
        $this->setAttribute('is_readable', $isReadable);
    }

    /**
     * max_connectionsを取得
     */
    public function getMaxConnections(): ?int
    {
        return $this->getAttribute('max_connections');
    }

    /**
     * max_connectionsを設定
     */
    public function setMaxConnections(int $maxConnections): void
    {
        $this->setAttribute('max_connections', $maxConnections);
    }

    /**
     * current_player_countを取得
     */
    public function getCurrentPlayerCount(): ?int
    {
        return $this->getAttribute('current_player_count');
    }

    /**
     * current_player_countを設定
     */
    public function setCurrentPlayerCount(int $currentPlayerCount): void
    {
        $this->setAttribute('current_player_count', $currentPlayerCount);
    }

    /**
     * シャーディング設定とのリレーション
     */
    public function sharding(): BelongsTo
    {
        return $this->belongsTo(SysSharding::class, 'sys_sharding_id');
    }

    /**
     * プレイヤー割り当てとのリレーション
     */
    public function playerAssignments(): HasMany
    {
        return $this->hasMany(SysShardingNodePlayer::class, 'sys_sharding_node_id');
    }

    /**
     * ノードがアクティブかチェック
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * ノードが書き込み可能かチェック
     */
    public function isWritable(): bool
    {
        return $this->is_writable === true && $this->isActive();
    }

    /**
     * ノードが読み込み可能かチェック
     */
    public function isReadable(): bool
    {
        return $this->is_readable === true && $this->isActive();
    }

    /**
     * ノードがメンテナンス中かチェック
     */
    public function isInMaintenance(): bool
    {
        return $this->status === self::STATUS_MAINTENANCE;
    }

    /**
     * ノードがプレイヤーを受け入れ可能かチェック
     */
    public function canAcceptPlayer(): bool
    {
        return $this->isActive()
            && $this->isWritable()
            && $this->current_player_count < $this->max_connections;
    }

    /**
     * ノードの使用率を取得（0-100）
     */
    public function getUsagePercentage(): float
    {
        if ($this->max_connections <= 0) {
            return 0.0;
        }

        return ($this->current_player_count / $this->max_connections) * 100;
    }

    /**
     * ノード番号から接続名を取得
     */
    public function getTrxConnectionName(): string
    {
        return "trx{$this->node_no}";
    }

    /**
     * データベース接続設定をconfig/database.phpから取得
     */
    public function getConnectionConfig(): ?array
    {
        return config("database.connections.{$this->getTrxConnectionName()}");
    }

    /**
     * プレイヤー数をインクリメント
     */
    public function incrementPlayerCount(): bool
    {
        return $this->increment('current_player_count') > 0;
    }

    /**
     * プレイヤー数をデクリメント
     */
    public function decrementPlayerCount(): bool
    {
        if ($this->current_player_count > 0) {
            return $this->decrement('current_player_count') > 0;
        }

        return false;
    }

    /**
     * レスポンス用配列に変換
     *
     * データベース層の'id'をAPI層の'sys_sharding_node_id'に変換
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        if (isset($array['id'])) {
            $array['sys_sharding_node_id'] = $array['id'];
            unset($array['id']);
        }

        return $array;
    }
}
