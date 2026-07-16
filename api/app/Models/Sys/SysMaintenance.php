<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Builder;
use NexusMaintenance\DTOs\SysMaintenance as SysMaintenanceDto;

/**
 * SysMaintenance Model
 *
 * メンテナンス情報管理テーブル
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
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * titleを取得
     *
     * @return string|null
     */
    public function getTitle(): ?string
    {
        return $this->getAttribute('title');
    }

    /**
     * titleを設定
     *
     * @param string $title
     * @return void
     */
    public function setTitle(string $title): void
    {
        $this->setAttribute('title', $title);
    }

    /**
     * messageを取得
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        return $this->getAttribute('message');
    }

    /**
     * messageを設定
     *
     * @param string $message
     * @return void
     */
    public function setMessage(string $message): void
    {
        $this->setAttribute('message', $message);
    }

    /**
     * start_atを取得
     *
     * @return \DateTime|null
     */
    public function getStartAt(): ?\DateTime
    {
        return $this->getAttribute('start_at');
    }

    /**
     * start_atを設定
     *
     * @param \DateTime|string $startAt
     * @return void
     */
    public function setStartAt(\DateTime|string $startAt): void
    {
        $this->setAttribute('start_at', $startAt);
    }

    /**
     * end_atを取得
     *
     * @return \DateTime|null
     */
    public function getEndAt(): ?\DateTime
    {
        return $this->getAttribute('end_at');
    }

    /**
     * end_atを設定
     *
     * @param \DateTime|string|null $endAt
     * @return void
     */
    public function setEndAt(\DateTime|string|null $endAt): void
    {
        $this->setAttribute('end_at', $endAt);
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
     * メンテナンスが有効かチェック
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * メンテナンス期間中かチェック
     *
     * @return bool
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
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 進行中のメンテナンスを取得（スコープ）
     *
     * @param Builder $query
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
     *
     * @return self|null
     */
    public static function getCurrentMaintenance(): ?self
    {
        return static::inProgress()
            ->orderBy('start_at', 'desc')
            ->first();
    }

    /**
     * SysMaintenanceDtoに変換
     *
     * @return SysMaintenanceDto
     */
    public function toDto(): SysMaintenanceDto
    {
        return new SysMaintenanceDto(
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
     * 
     * @return array
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
