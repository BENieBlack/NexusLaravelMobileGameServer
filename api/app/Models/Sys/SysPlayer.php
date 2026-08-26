<?php

namespace App\Models\Sys;

use App\Models\Mst\MstPlayerLevel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Nexus\Core\Contracts\PlayerModelInterface;
use Nexus\Core\Utilities\ClockUtility;

/**
 * SysPlayer Model
 *
 * プレイヤーマスターテーブル
 * プレイヤーの基本情報を管理
 *
 * @property int $id
 * @property string $uuid
 * @property string $my_id
 * @property string $name
 * @property int $level
 * @property int $level_exp
 * @property int $vip_point
 * @property string $total_paid_amount
 * @property ?string $created_at
 * @property ?string $last_login_at
 */
class SysPlayer extends _BaseSys implements PlayerModelInterface
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
    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'my_id',
        'name',
        'level',
        'level_exp',
        'vip_point',
        'total_paid_amount',
        'last_login_at',
    ];

    /**
     * キャストする属性
     *
     * @var array<string, string>
     */
    /** @var array<string, string> */
    protected $casts = [
        'level' => 'integer',
        'level_exp' => 'integer',
        'vip_point' => 'integer',
        'total_paid_amount' => 'decimal:2',
    ];

    /**
     * デバイス情報とのリレーション
     */
    /**
     * @return HasMany<SysPlayerDevice, $this>
     */
    public function devices(): HasMany
    {
        return $this->hasMany(SysPlayerDevice::class, 'player_id');
    }

    /**
     * トークン情報とのリレーション
     */
    /**
     * @return HasMany<SysPlayerToken, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(SysPlayerToken::class, 'player_id');
    }

    /**
     * UUIDからプレイヤーを取得
     */
    public static function findByUuid(string $uuid): ?self
    {
        return static::where('uuid', $uuid)->first();
    }

    /**
     * my_idからプレイヤーを取得
     */
    public static function findByMyId(string $myId): ?self
    {
        return static::where('my_id', $myId)->first();
    }

    /**
     * プレイヤーIDを取得
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * UUIDを取得
     */
    public function getUuid(): string
    {
        return $this->getAttribute('uuid');
    }

    /**
     * My IDを取得
     */
    public function getMyId(): string
    {
        return $this->getAttribute('my_id');
    }

    /**
     * プレイヤー名を取得
     */
    public function getName(): ?string
    {
        return $this->getAttribute('name');
    }

    /**
     * レベルを取得
     */
    public function getLevel(): int
    {
        return $this->getAttribute('level');
    }

    /**
     * レベル経験値を取得
     */
    public function getLevelExp(): int
    {
        return $this->getAttribute('level_exp');
    }

    /**
     * 最終ログイン日時を取得
     */
    public function getLastLoginAt(): ?string
    {
        return $this->getAttribute('last_login_at');
    }

    /**
     * UUIDを設定
     */
    public function setUuid(string $uuid): void
    {
        $this->setAttribute('uuid', $uuid);
    }

    /**
     * My IDを設定
     */
    public function setMyId(string $myId): void
    {
        $this->setAttribute('my_id', $myId);
    }

    /**
     * プレイヤー名を設定
     */
    public function setName(string $name): void
    {
        $this->setAttribute('name', $name);
    }

    /**
     * レベルを設定
     */
    public function setLevel(int $level): void
    {
        $this->setAttribute('level', $level);
    }

    /**
     * レベル経験値を設定
     */
    public function setLevelExp(int $levelExp): void
    {
        $this->setAttribute('level_exp', $levelExp);
    }

    /**
     * 最終ログイン日時を設定
     */
    public function setLastLoginAt(string $lastLoginAt): void
    {
        $this->setAttribute('last_login_at', $lastLoginAt);
    }

    /**
     * 次のレベルまでに必要な経験値を取得
     *
     * @return int 必要な経験値（レベルが最大の場合は0）
     */
    public function calcExpToNextLevel(): int
    {
        $nextLevel = $this->getLevel() + 1;
        $nextLevelData = MstPlayerLevel::findByLevel($nextLevel);

        if ($nextLevelData === null) {
            // 最大レベルに達している
            return 0;
        }

        return max(0, $nextLevelData->getRequiredExp() - $this->getLevelExp());
    }

    /**
     * 現在のレベルのマスターデータを取得
     */
    public function getCurrentLevelData(): ?MstPlayerLevel
    {
        return MstPlayerLevel::findByLevel($this->getLevel());
    }

    /**
     * 現在のレベルの最大スタミナを取得
     */
    public function getMaxStamina(): ?int
    {
        $levelData = $this->getCurrentLevelData();

        return $levelData?->getMaxStamina();
    }

    /**
     * レスポンス用配列に変換
     *
     * データベース層の'id'は除外（内部IDのため）
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        // 内部IDは除外（parent::toResponseArray()で既に除外されている）
        unset($array['id']);

        return $array;
    }

    /**
     * 作成日時取得
     * PlayerModelInterface implementation
     *
     * @return string Y-m-d H:i:s形式
     */
    public function getCreatedAt(): string
    {
        return ClockUtility::parse((string) $this->created_at)->format('Y-m-d H:i:s');
    }

    /**
     * VIPポイントを取得
     */
    public function getVipPoint(): int
    {
        return $this->getAttribute('vip_point');
    }

    /**
     * 累積課金額を取得
     */
    public function getTotalPaidAmount(): float
    {
        return (float) $this->getAttribute('total_paid_amount');
    }

    /**
     * VIPポイントを設定
     */
    public function setVipPoint(int $point): void
    {
        $this->setAttribute('vip_point', $point);
    }

    /**
     * 累積課金額を設定
     */
    public function setTotalPaidAmount(float $amount): void
    {
        $this->setAttribute('total_paid_amount', $amount);
    }

    /**
     * VIPポイントを加算
     */
    public function addVipPoint(int $points): void
    {
        $currentPoint = $this->getVipPoint();
        $this->setVipPoint($currentPoint + $points);
    }

    /**
     * 累積課金額を加算
     */
    public function addTotalPaidAmount(float $amount): void
    {
        $current = $this->getTotalPaidAmount();
        $this->setTotalPaidAmount($current + $amount);
    }
}
