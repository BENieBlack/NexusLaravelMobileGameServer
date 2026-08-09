<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SysDeploy Model
 *
 * デプロイ管理テーブル
 * マスターデータとアセットデータの配信バージョンを管理
 */
class SysDeploy extends _BaseSys
{
    /**
     * テーブル名
     */
    protected $table = 'sys_deploy';

    /**
     * 複数代入可能な属性
     *
     * @var array
     */
    protected $fillable = [
        'deploy_key',
        'start_at',
        'sys_deploy_master_id',
        'sys_deploy_asset_id',
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
     * マスターデプロイとのリレーション
     */
    public function deployMaster(): BelongsTo
    {
        return $this->belongsTo(SysDeployMaster::class, 'sys_deploy_master_id');
    }

    /**
     * アセットデプロイとのリレーション
     */
    public function deployAsset(): BelongsTo
    {
        return $this->belongsTo(SysDeployAsset::class, 'sys_deploy_asset_id');
    }

    /**
     * deploy_keyを取得
     */
    public function getDeployKey(): ?int
    {
        return $this->getAttribute('deploy_key');
    }

    /**
     * deploy_keyを設定
     */
    public function setDeployKey(int $deployKey): void
    {
        $this->setAttribute('deploy_key', $deployKey);
    }

    /**
     * start_atを取得
     */
    public function getStartAt(): ?\DateTime
    {
        return $this->getAttribute('start_at');
    }

    /**
     * start_atを設定
     */
    public function setStartAt(\DateTime|string $startAt): void
    {
        $this->setAttribute('start_at', $startAt);
    }

    /**
     * sys_deploy_master_idを取得
     */
    public function getSysDeployMasterId(): ?int
    {
        return $this->getAttribute('sys_deploy_master_id');
    }

    /**
     * sys_deploy_master_idを設定
     */
    public function setSysDeployMasterId(int $sysDeployMasterId): void
    {
        $this->setAttribute('sys_deploy_master_id', $sysDeployMasterId);
    }

    /**
     * sys_deploy_asset_idを取得
     */
    public function getSysDeployAssetId(): ?int
    {
        return $this->getAttribute('sys_deploy_asset_id');
    }

    /**
     * sys_deploy_asset_idを設定
     */
    public function setSysDeployAssetId(int $sysDeployAssetId): void
    {
        $this->setAttribute('sys_deploy_asset_id', $sysDeployAssetId);
    }

    /**
     * is_activeを設定
     */
    public function setIsActive(bool $isActive): void
    {
        $this->setAttribute('is_active', $isActive);
    }

    /**
     * デプロイキーから年月日とカウントを取得
     *
     * @return array{year: int, month: int, day: int, count: int}
     */
    public function parseDeployKey(): array
    {
        $keyString = (string) $this->deploy_key;

        return [
            'year' => (int) substr($keyString, 0, 4),
            'month' => (int) substr($keyString, 4, 2),
            'day' => (int) substr($keyString, 6, 2),
            'count' => (int) substr($keyString, 8, 1),
        ];
    }

    /**
     * デプロイが有効かチェック
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * デプロイが配信開始済みかチェック
     */
    public function isStarted(): bool
    {
        return $this->start_at <= now();
    }

    /**
     * デプロイがダウンロード可能かチェック
     * 有効かつ配信開始日時を過ぎている場合にtrue
     */
    public function isDownloadable(): bool
    {
        return $this->isActive() && $this->isStarted();
    }

    /**
     * 現在有効なデプロイを取得（スコープ）
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * ダウンロード可能なデプロイを取得（スコープ）
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function scopeDownloadable($query)
    {
        return $query->where('is_active', true)
            ->where('start_at', '<=', now());
    }

    /**
     * 最新のダウンロード可能なデプロイを取得
     */
    public static function getLatestDownloadable(): ?self
    {
        return static::downloadable()
            ->orderBy('deploy_key', 'desc')
            ->first();
    }

    /**
     * レスポンス用配列に変換
     *
     * データベース層の'id'をAPI層の'sys_deploy_id'に変換
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        if (isset($array['id'])) {
            $array['sys_deploy_id'] = $array['id'];
            unset($array['id']);
        }

        return $array;
    }
}
