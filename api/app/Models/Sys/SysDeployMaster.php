<?php

namespace App\Models\Sys;

/**
 * SysDeployMaster Model
 *
 * マスターデータのデプロイ履歴を管理するモデル
 *
 * @property int $deploy_key
 * @property string $hash
 * @property string $status
 */
class SysDeployMaster extends _BaseSys
{
    /**
     * テーブル名
     */
    protected $table = 'sys_deploy_master';

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
     */
    public static function availableStatuses(): array
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
     * hashを取得
     */
    public function getHash(): ?string
    {
        return $this->getAttribute('hash');
    }

    /**
     * hashを設定
     */
    public function setHash(string $hash): void
    {
        $this->setAttribute('hash', $hash);
    }

    /**
     * deploy_dateを取得
     */
    public function getDeployDate(): ?\DateTime
    {
        return $this->getAttribute('deploy_date');
    }

    /**
     * deploy_dateを設定
     */
    public function setDeployDate(\DateTime|string $deployDate): void
    {
        $this->setAttribute('deploy_date', $deployDate);
    }

    /**
     * deploy_countを取得
     */
    public function getDeployCount(): ?int
    {
        return $this->getAttribute('deploy_count');
    }

    /**
     * deploy_countを設定
     */
    public function setDeployCount(int $deployCount): void
    {
        $this->setAttribute('deploy_count', $deployCount);
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
     * deployed_byを取得
     */
    public function getDeployedBy(): ?string
    {
        return $this->getAttribute('deployed_by');
    }

    /**
     * deployed_byを設定
     */
    public function setDeployedBy(string $deployedBy): void
    {
        $this->setAttribute('deployed_by', $deployedBy);
    }

    /**
     * deployed_atを取得
     */
    public function getDeployedAt(): ?\DateTime
    {
        return $this->getAttribute('deployed_at');
    }

    /**
     * deployed_atを設定
     */
    public function setDeployedAt(\DateTime|string $deployedAt): void
    {
        $this->setAttribute('deployed_at', $deployedAt);
    }

    /**
     * descriptionを取得
     */
    public function getDescription(): ?string
    {
        return $this->getAttribute('description');
    }

    /**
     * descriptionを設定
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
     * デプロイが完了しているかチェック
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * デプロイが失敗しているかチェック
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * デプロイがロールバック済みかチェック
     */
    public function isRolledBack(): bool
    {
        return $this->status === self::STATUS_ROLLED_BACK;
    }

    /**
     * ハッシュが有効かチェック
     */
    public function hasValidHash(): bool
    {
        return ! empty($this->hash) && strlen($this->hash) === 64;
    }

    /**
     * ハッシュからバージョン文字列を生成（短縮版）
     * セキュリティのため、ハッシュの最初の8文字のみを使用
     */
    public function buildVersionString(): ?string
    {
        if (! $this->hasValidHash()) {
            return null;
        }

        return substr($this->hash, 0, 8);
    }

    /**
     * レスポンス用配列に変換
     *
     * データベース層の'id'をAPI層の'sys_deploy_master_id'に変換
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        if (isset($array['id'])) {
            $array['sys_deploy_master_id'] = $array['id'];
            unset($array['id']);
        }

        return $array;
    }
}
