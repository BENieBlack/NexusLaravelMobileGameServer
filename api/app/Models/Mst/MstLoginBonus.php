<?php

namespace App\Models\Mst;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MstLoginBonus Model
 *
 * @property int $deploy_key
 * @property string $id
 * @property string $type
 * @property int $day
 * @property int $loop_days
 * @property int|null $required_absent_days
 * @property int|null $valid_days
 * @property int $priority
 * @property bool $is_active
 * @property \DateTimeImmutable|null $start_at
 * @property \DateTimeImmutable|null $end_at
 * @property \DateTimeImmutable $created_at
 * @property \DateTimeImmutable $updated_at
 */
class MstLoginBonus extends _BaseMst
{
    public const TYPE_DAILY = 'daily';

    public const TYPE_COMEBACK = 'comeback';

    public $table = 'mst_login_bonus';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var array<int, string> */
    protected $fillable = [
        'deploy_key',
        'id',
        'type',
        'day',
        'loop_days',
        'required_absent_days',
        'valid_days',
        'priority',
        'is_active',
        'start_at',
        'end_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'deploy_key' => 'integer',
        'day' => 'integer',
        'loop_days' => 'integer',
        'required_absent_days' => 'integer',
        'valid_days' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'start_at' => 'immutable_datetime',
        'end_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    public $timestamps = true;

    /**
     * ログインボーナス報酬内容とのリレーション
     */
    public function contents(): HasMany
    {
        return $this->hasMany(MstLoginBonusContent::class, 'mst_login_bonus_id', 'id')
            ->orderBy('sort_order', 'asc');
    }

    /**
     * 通常ログインボーナスかどうか
     */
    public function isDailyType(): bool
    {
        return $this->type === self::TYPE_DAILY;
    }

    /**
     * カムバックログインボーナスかどうか
     */
    public function isComebackType(): bool
    {
        return $this->type === self::TYPE_COMEBACK;
    }

    /**
     * 必要休眠日数を取得（カムバック用）
     */
    public function getRequiredAbsentDays(): ?int
    {
        return $this->required_absent_days;
    }

    /**
     * 有効期間を取得（カムバック用）
     */
    public function getValidDays(): ?int
    {
        return $this->valid_days;
    }

    /**
     * 優先度を取得
     */
    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * 期間限定ボーナスが有効期間内かチェック
     */
    public function isWithinPeriod(?\DateTimeImmutable $now = null): bool
    {
        $now = $now ?? new \DateTimeImmutable;

        if ($this->start_at !== null && $now < $this->start_at) {
            return false;
        }

        if ($this->end_at !== null && $now > $this->end_at) {
            return false;
        }

        return true;
    }

    /**
     * スコープ: 通常ログインボーナスのみ
     */
    public function scopeDailyType($query)
    {
        return $query->where('type', self::TYPE_DAILY);
    }

    /**
     * スコープ: カムバックログインボーナスのみ
     */
    public function scopeComebackType($query)
    {
        return $query->where('type', self::TYPE_COMEBACK);
    }

    /**
     * スコープ: 有効なボーナスのみ
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
