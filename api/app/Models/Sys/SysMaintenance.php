<?php

namespace App\Models\Sys;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use NexusMaintenance\ValueObjects\Maintenance;

/**
 * SysMaintenance Model
 *
 * メンテナンス情報管理テーブル
 *
 * @property ?string $end_at
 * @property bool $is_active
 * @property ?string $message
 * @property ?string $start_at
 * @property ?string $title
 * @property ?string $updated_at
 */
class SysMaintenance extends _BaseSys
{
    /**
     * テーブル名
     */
    protected $table = 'sys_maintenance';

    /**
     * 複数代入可能な属性
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'message',
        'start_at',
        'end_at',
        'is_active',
    ];

    /**
     * キャストする属性
     *
     * @var array
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * titleを取得
     */
    public function getTitle(): ?string
    {
        return $this->getAttribute('title');
    }

    /**
     * titleを設定
     */
    public function setTitle(string $title): void
    {
        $this->setAttribute('title', $title);
    }

    /**
     * messageを取得
     */
    public function getMessage(): ?string
    {
        return $this->getAttribute('message');
    }

    /**
     * messageを設定
     */
    public function setMessage(string $message): void
    {
        $this->setAttribute('message', $message);
    }

    /**
     * start_atを取得
     *
     * DB取得時はstring型で保持されているため、CarbonImmutable型に変換して返す
     */
    public function getStartAt(): ?CarbonImmutable
    {
        return $this->getDateAttribute('start_at');
    }

    /**
     * start_atを設定
     */
    public function setStartAt(\DateTimeInterface|string $startAt): void
    {
        $this->setAttribute('start_at', $startAt);
    }

    /**
     * end_atを取得
     *
     * DB取得時はstring型で保持されているため、CarbonImmutable型に変換して返す
     */
    public function getEndAt(): ?CarbonImmutable
    {
        return $this->getDateAttribute('end_at');
    }

    /**
     * end_atを設定
     */
    public function setEndAt(\DateTimeInterface|string|null $endAt): void
    {
        $this->setAttribute('end_at', $endAt);
    }

    /**
     * is_activeを設定
     */
    public function setIsActive(bool $isActive): void
    {
        $this->setAttribute('is_active', $isActive);
    }

    /**
     * メンテナンスが有効かチェック
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * メンテナンス期間中かチェック
     */
    public function isInProgress(): bool
    {
        $now = now();
        $hasStarted = $this->start_at <= $now;
        $hasNotEnded = $this->end_at === null || $this->end_at > $now;

        return $hasStarted && $hasNotEnded;
    }

    /**
     * 現在有効なメンテナンスを取得（スコープ）
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 進行中のメンテナンスを取得（スコープ）
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeInProgress($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where('start_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')
                    ->orWhere('end_at', '>', $now);
            });
    }

    /**
     * 現在進行中のメンテナンスを取得
     */
    public static function selectCurrentMaintenance(): ?self
    {
        return static::inProgress()
            ->orderBy('start_at', 'desc')
            ->first();
    }

    /**
     * Maintenanceに変換
     */
    public function toDto(): Maintenance
    {
        return new Maintenance(
            isMaintenance: $this->is_active,
            startAt: $this->start_at?->format('Y-m-d H:i:s'),
            endAt: $this->end_at?->format('Y-m-d H:i:s'),
            title: $this->title,
            message: $this->message,
            updatedAt: $this->updated_at?->format('Y-m-d H:i:s'),
        );
    }

    /**
     * レスポンス用配列に変換
     *
     * データベース層の'id'をAPI層の'sys_maintenance_id'に変換
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        if (isset($array['id'])) {
            $array['sys_maintenance_id'] = $array['id'];
            unset($array['id']);
        }

        return $array;
    }
}
