<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SysPlayer Model
 * 
 * プレイヤーマスターテーブル
 * プレイヤーの基本情報を管理
 */
class SysPlayer extends _BaseSys
{

    /**
     * テーブル名
     */
    protected $table = 'sys_player';

    /**
     * 複数代入可能な属性
     *
     * @var array<string>
     */
    protected $fillable = [
        'uuid',
        'my_id',
        'name',
        'level',
        'level_exp',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    protected $casts = [
        'level' => 'integer',
        'level_exp' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * デバイス情報とのリレーション
     *
     * @return HasMany
     */
    public function devices(): HasMany
    {
        return $this->hasMany(SysPlayerDevice::class, 'player_id');
    }

    /**
     * トークン情報とのリレーション
     *
     * @return HasMany
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(SysPlayerToken::class, 'player_id');
    }

    /**
     * UUIDからプレイヤーを取得
     *
     * @param string $uuid
     * @return self|null
     */
    public static function findByUuid(string $uuid): ?self
    {
        return static::where('uuid', $uuid)->first();
    }

    /**
     * my_idからプレイヤーを取得
     *
     * @param string $myId
     * @return self|null
     */
    public static function findByMyId(string $myId): ?self
    {
        return static::where('my_id', $myId)->first();
    }

    /**
     * プレイヤーIDを取得
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * UUIDを取得
     *
     * @return string
     */
    public function getUuid(): string
    {
        return $this->getAttribute('uuid');
    }

    /**
     * My IDを取得
     *
     * @return string
     */
    public function getMyId(): string
    {
        return $this->getAttribute('my_id');
    }

    /**
     * プレイヤー名を取得
     *
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->getAttribute('name');
    }

    /**
     * レベルを取得
     *
     * @return int
     */
    public function getLevel(): int
    {
        return $this->getAttribute('level');
    }

    /**
     * レベル経験値を取得
     *
     * @return int
     */
    public function getLevelExp(): int
    {
        return $this->getAttribute('level_exp');
    }

    /**
     * UUIDを設定
     *
     * @param string $uuid
     * @return void
     */
    public function setUuid(string $uuid): void
    {
        $this->setAttribute('uuid', $uuid);
    }

    /**
     * My IDを設定
     *
     * @param string $myId
     * @return void
     */
    public function setMyId(string $myId): void
    {
        $this->setAttribute('my_id', $myId);
    }

    /**
     * プレイヤー名を設定
     *
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        $this->setAttribute('name', $name);
    }

    /**
     * レベルを設定
     *
     * @param int $level
     * @return void
     */
    public function setLevel(int $level): void
    {
        $this->setAttribute('level', $level);
    }

    /**
     * レベル経験値を設定
     *
     * @param int $levelExp
     * @return void
     */
    public function setLevelExp(int $levelExp): void
    {
        $this->setAttribute('level_exp', $levelExp);
    }

    /**
     * 次のレベルまでに必要な経験値を取得
     * 
     * @return int 必要な経験値（レベルが最大の場合は0）
     */
    public function getExpToNextLevel(): int
    {
        $nextLevel = $this->getLevel() + 1;
        $nextLevelData = \App\Models\Mst\MstPlayerLevel::findByLevel($nextLevel);
        
        if ($nextLevelData === null) {
            // 最大レベルに達している
            return 0;
        }
        
        return max(0, $nextLevelData->getRequiredExp() - $this->getLevelExp());
    }

    /**
     * 現在のレベルのマスターデータを取得
     * 
     * @return \App\Models\Mst\MstPlayerLevel|null
     */
    public function getCurrentLevelData(): ?\App\Models\Mst\MstPlayerLevel
    {
        return \App\Models\Mst\MstPlayerLevel::findByLevel($this->getLevel());
    }

    /**
     * 現在のレベルの最大スタミナを取得
     * 
     * @return int|null
     */
    public function getMaxStamina(): ?int
    {
        $levelData = $this->getCurrentLevelData();
        return $levelData?->getMaxStamina();
    }

    /**
     * レスポンス用配列に変換
     * 
     * データベース層の'id'をAPI層の'sys_player_id'に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();
        
        if (isset($array['id'])) {
            $array['sys_player_id'] = $array['id'];
            unset($array['id']);
        }
        
        return $array;
    }
}
