<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SysSharding Model
 * 
 * シャーディング設定を管理するモデル
 */
class SysSharding extends _BaseSys
{

    /**
     * テーブル名
     */
    protected $table = 'sys_sharding';

    /**
     * 複数代入可能な属性
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'target',
        'strategy',
        'sharding_key',
        'node_count',
        'is_active',
        'description',
    ];

    /**
     * キャストする属性
     *
     * @var array
     */
    protected $casts = [
        'node_count' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * シャーディング戦略の定数
     */
    public const STRATEGY_HASH = 'hash';
    public const STRATEGY_RANGE = 'range';
    public const STRATEGY_CONSISTENT = 'consistent';

    /**
     * シャーディング対象の定数
     */
    public const TARGET_TRANSACTION = 'transaction';
    public const TARGET_LOG = 'log';

    /**
     * 利用可能なシャーディング戦略一覧を取得
     *
     * @return array
     */
    public static function getAvailableStrategies(): array
    {
        return [
            self::STRATEGY_HASH,
            self::STRATEGY_RANGE,
            self::STRATEGY_CONSISTENT,
        ];
    }

    /**
     * 利用可能なシャーディング対象一覧を取得
     *
     * @return array
     */
    public static function getAvailableTargets(): array
    {
        return [
            self::TARGET_TRANSACTION,
            self::TARGET_LOG,
        ];
    }

    /**
     * nameを取得
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->getAttribute('name');
    }

    /**
     * nameを設定
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->setAttribute('name', $name);
    }

    /**
     * targetを取得
     *
     * @return string|null
     */
    public function getTarget(): ?string
    {
        return $this->getAttribute('target');
    }

    /**
     * targetを設定
     *
     * @param string $target
     * @return void
     */
    public function setTarget(string $target): void
    {
        $this->setAttribute('target', $target);
    }

    /**
     * strategyを取得
     *
     * @return string|null
     */
    public function getStrategy(): ?string
    {
        return $this->getAttribute('strategy');
    }

    /**
     * strategyを設定
     *
     * @param string $strategy
     * @return void
     */
    public function setStrategy(string $strategy): void
    {
        $this->setAttribute('strategy', $strategy);
    }

    /**
     * sharding_keyを取得
     *
     * @return string|null
     */
    public function getShardingKey(): ?string
    {
        return $this->getAttribute('sharding_key');
    }

    /**
     * sharding_keyを設定
     *
     * @param string $shardingKey
     * @return void
     */
    public function setShardingKey(string $shardingKey): void
    {
        $this->setAttribute('sharding_key', $shardingKey);
    }

    /**
     * node_countを取得
     *
     * @return int|null
     */
    public function getNodeCount(): ?int
    {
        return $this->getAttribute('node_count');
    }

    /**
     * node_countを設定
     *
     * @param int $nodeCount
     * @return void
     */
    public function setNodeCount(int $nodeCount): void
    {
        $this->setAttribute('node_count', $nodeCount);
    }

    /**
     * is_activeを設定
     *
     * @param bool $isActive
     * @return void
     */
    public function setIsActive(bool $isActive): void
    {
        $this->setAttribute('is_active', $isActive);
    }

    /**
     * descriptionを取得
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->getAttribute('description');
    }

    /**
     * descriptionを設定
     *
     * @param string|null $description
     * @return void
     */
    public function setDescription(?string $description): void
    {
        $this->setAttribute('description', $description);
    }

    /**
     * シャーディングノードとのリレーション
     *
     * @return HasMany
     */
    public function nodes(): HasMany
    {
        return $this->hasMany(SysShardingNode::class, 'sys_sharding_id');
    }

    /**
     * アクティブなシャーディングノードを取得
     *
     * @return HasMany
     */
    public function activeNodes(): HasMany
    {
        return $this->nodes()->where('status', SysShardingNode::STATUS_ACTIVE);
    }

    /**
     * シャーディングがアクティブかチェック
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * ハッシュ戦略かチェック
     *
     * @return bool
     */
    public function isHashStrategy(): bool
    {
        return $this->strategy === self::STRATEGY_HASH;
    }

    /**
     * レンジ戦略かチェック
     *
     * @return bool
     */
    public function isRangeStrategy(): bool
    {
        return $this->strategy === self::STRATEGY_RANGE;
    }

    /**
     * コンシステントハッシュ戦略かチェック
     *
     * @return bool
     */
    public function isConsistentStrategy(): bool
    {
        return $this->strategy === self::STRATEGY_CONSISTENT;
    }

    /**
     * レスポンス用配列に変換
     * 
     * データベース層の'id'をAPI層の'sys_sharding_id'に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();
        
        if (isset($array['id'])) {
            $array['sys_sharding_id'] = $array['id'];
            unset($array['id']);
        }
        
        return $array;
    }
}
