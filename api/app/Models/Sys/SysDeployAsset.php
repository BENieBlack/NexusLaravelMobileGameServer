<?php

namespace App\Models\Sys;


/**
 * SysDeployAsset Model
 * 
 * アセットデータ（画像、音声、動画など）のデプロイ履歴を管理するモデル
 */
class SysDeployAsset extends _BaseSys
{

    /**
     * テーブル名
     */
    protected $table = 'sys_deploy_asset';

    /**
     * 複数代入可能な属性
     *
     * @var array
     */
    protected $fillable = [
        'deploy_key',
        'hash',
        'deploy_date',
        'deploy_count',
        'status',
        's3_bucket',
        's3_path',
        'asset_version',
        'total_size',
        'file_count',
        'deployed_by',
        'deployed_at',
        'description',
    ];

    /**
     * キャストする属性
     *
     * @var array
     */
    protected $casts = [
        'deploy_date' => 'date',
        'deployed_at' => 'datetime',
        'total_size' => 'integer',
        'file_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * デプロイステータスの定数
     */
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    /**
     * 利用可能なステータス一覧を取得
     *
     * @return array
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_SCHEDULED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_ROLLED_BACK,
        ];
    }

    /**
     * deploy_keyを取得
     *
     * @return int|null
     */
    public function getDeployKey(): ?int
    {
        return $this->getAttribute('deploy_key');
    }

    /**
     * deploy_keyを設定
     *
     * @param int $deployKey
     * @return void
     */
    public function setDeployKey(int $deployKey): void
    {
        $this->setAttribute('deploy_key', $deployKey);
    }

    /**
     * hashを取得
     *
     * @return string|null
     */
    public function getHash(): ?string
    {
        return $this->getAttribute('hash');
    }

    /**
     * hashを設定
     *
     * @param string $hash
     * @return void
     */
    public function setHash(string $hash): void
    {
        $this->setAttribute('hash', $hash);
    }

    /**
     * deploy_dateを取得
     *
     * @return \DateTime|null
     */
    public function getDeployDate(): ?\DateTime
    {
        return $this->getAttribute('deploy_date');
    }

    /**
     * deploy_dateを設定
     *
     * @param \DateTime|string $deployDate
     * @return void
     */
    public function setDeployDate(\DateTime|string $deployDate): void
    {
        $this->setAttribute('deploy_date', $deployDate);
    }

    /**
     * deploy_countを取得
     *
     * @return int|null
     */
    public function getDeployCount(): ?int
    {
        return $this->getAttribute('deploy_count');
    }

    /**
     * deploy_countを設定
     *
     * @param int $deployCount
     * @return void
     */
    public function setDeployCount(int $deployCount): void
    {
        $this->setAttribute('deploy_count', $deployCount);
    }

    /**
     * statusを取得
     *
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->getAttribute('status');
    }

    /**
     * statusを設定
     *
     * @param string $status
     * @return void
     */
    public function setStatus(string $status): void
    {
        $this->setAttribute('status', $status);
    }

    /**
     * s3_bucketを取得
     *
     * @return string|null
     */
    public function getS3Bucket(): ?string
    {
        return $this->getAttribute('s3_bucket');
    }

    /**
     * s3_bucketを設定
     *
     * @param string $s3Bucket
     * @return void
     */
    public function setS3Bucket(string $s3Bucket): void
    {
        $this->setAttribute('s3_bucket', $s3Bucket);
    }

    /**
     * s3_pathを取得
     *
     * @return string|null
     */
    public function getS3Path(): ?string
    {
        return $this->getAttribute('s3_path');
    }

    /**
     * s3_pathを設定
     *
     * @param string $s3Path
     * @return void
     */
    public function setS3Path(string $s3Path): void
    {
        $this->setAttribute('s3_path', $s3Path);
    }

    /**
     * asset_versionを取得
     *
     * @return string|null
     */
    public function getAssetVersion(): ?string
    {
        return $this->getAttribute('asset_version');
    }

    /**
     * asset_versionを設定
     *
     * @param string $assetVersion
     * @return void
     */
    public function setAssetVersion(string $assetVersion): void
    {
        $this->setAttribute('asset_version', $assetVersion);
    }

    /**
     * total_sizeを取得
     *
     * @return int|null
     */
    public function getTotalSize(): ?int
    {
        return $this->getAttribute('total_size');
    }

    /**
     * total_sizeを設定
     *
     * @param int $totalSize
     * @return void
     */
    public function setTotalSize(int $totalSize): void
    {
        $this->setAttribute('total_size', $totalSize);
    }

    /**
     * file_countを取得
     *
     * @return int|null
     */
    public function getFileCount(): ?int
    {
        return $this->getAttribute('file_count');
    }

    /**
     * file_countを設定
     *
     * @param int $fileCount
     * @return void
     */
    public function setFileCount(int $fileCount): void
    {
        $this->setAttribute('file_count', $fileCount);
    }

    /**
     * deployed_byを取得
     *
     * @return string|null
     */
    public function getDeployedBy(): ?string
    {
        return $this->getAttribute('deployed_by');
    }

    /**
     * deployed_byを設定
     *
     * @param string $deployedBy
     * @return void
     */
    public function setDeployedBy(string $deployedBy): void
    {
        $this->setAttribute('deployed_by', $deployedBy);
    }

    /**
     * deployed_atを取得
     *
     * @return \DateTime|null
     */
    public function getDeployedAt(): ?\DateTime
    {
        return $this->getAttribute('deployed_at');
    }

    /**
     * deployed_atを設定
     *
     * @param \DateTime|string $deployedAt
     * @return void
     */
    public function setDeployedAt(\DateTime|string $deployedAt): void
    {
        $this->setAttribute('deployed_at', $deployedAt);
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
     * @param string $description
     * @return void
     */
    public function setDescription(string $description): void
    {
        $this->setAttribute('description', $description);
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
     * S3の完全URLを取得
     *
     * @return string|null
     */
    public function getS3FullUrl(): ?string
    {
        if (!$this->s3_bucket || !$this->s3_path) {
            return null;
        }

        return "s3://{$this->s3_bucket}/{$this->s3_path}";
    }

    /**
     * デプロイが完了しているかチェック
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * デプロイが失敗しているかチェック
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * デプロイがロールバック済みかチェック
     *
     * @return bool
     */
    public function isRolledBack(): bool
    {
        return $this->status === self::STATUS_ROLLED_BACK;
    }

    /**
     * 人間が読みやすい形式でファイルサイズを取得
     *
     * @return string|null
     */
    public function getHumanReadableSize(): ?string
    {
        if ($this->total_size === null) {
            return null;
        }

        $sizeUnitArray = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $this->total_size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($sizeUnitArray) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $sizeUnitArray[$unitIndex];
    }

    /**
     * ハッシュが有効かチェック
     *
     * @return bool
     */
    public function hasValidHash(): bool
    {
        return !empty($this->hash) && strlen($this->hash) === 64;
    }

    /**
     * ハッシュからバージョン文字列を生成（短縮版）
     * セキュリティのため、ハッシュの最初の8文字のみを使用
     *
     * @return string|null
     */
    public function getVersionString(): ?string
    {
        if (!$this->hasValidHash()) {
            return null;
        }

        return substr($this->hash, 0, 8);
    }

    /**
     * レスポンス用配列に変換
     * 
     * データベース層の'id'をAPI層の'sys_deploy_asset_id'に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();
        
        if (isset($array['id'])) {
            $array['sys_deploy_asset_id'] = $array['id'];
            unset($array['id']);
        }
        
        return $array;
    }
}
