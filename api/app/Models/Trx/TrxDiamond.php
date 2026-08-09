<?php

namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxDiamond Model
 *
 * ダイヤモンド現在値を管理するモデル
 * PRIMARY KEY: (sys_player_id, platform)
 *
 * platform: プラットフォーム（Apple, Google）
 * paid_amount: 有償ダイヤモンド数
 * free_amount: 無償ダイヤモンド数
 */
class TrxDiamond extends _BaseTrx
{
    protected $table = 'trx_diamond';

    /**
     * 複合主キーのため、auto incrementを無効化
     */
    public $incrementing = false;

    /**
     * 複合主キーの指定
     */
    protected $primaryKey = ['sys_player_id', 'platform'];

    /**
     * 主キーの型
     */
    protected $keyType = 'string';

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（複合キーで一意）
     */
    protected array $uniqueKeys = ['sys_player_id', 'platform'];

    protected $fillable = [
        'sys_player_id',
        'platform',
        'paid_amount',
        'free_amount',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'sys_player_id' => 'integer',
        'paid_amount' => 'integer',
        'free_amount' => 'integer',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];

    /**
     * 複合主キーを設定
     *
     * @param  Builder  $query
     * @return Builder
     */
    public function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (! is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * 複合主キーの値を取得
     *
     * @param  string|null  $keyName
     * @return mixed
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }

    /**
     * trx_playerとのリレーション
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * 有償ダイヤモンド数を取得
     */
    public function getPaidAmount(): int
    {
        return $this->getAttribute('paid_amount');
    }

    /**
     * 有償ダイヤモンド数を設定
     */
    public function setPaidAmount(int $paidAmount): void
    {
        $this->setAttribute('paid_amount', $paidAmount);
    }

    /**
     * 無償ダイヤモンド数を取得
     */
    public function getFreeAmount(): int
    {
        return $this->getAttribute('free_amount');
    }

    /**
     * 無償ダイヤモンド数を設定
     */
    public function setFreeAmount(int $freeAmount): void
    {
        $this->setAttribute('free_amount', $freeAmount);
    }

    /**
     * プラットフォームを設定
     */
    public function setPlatform(string $platform): void
    {
        $this->setAttribute('platform', $platform);
    }

    /**
     * システムプレイヤーIDを設定
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * レスポンス用配列に変換
     *
     * Note: 複合主キー(sys_player_id, platform)のため、idフィールドは存在しない
     */
    public function toResponseArray(): array
    {
        return parent::toResponseArray();
    }
}
