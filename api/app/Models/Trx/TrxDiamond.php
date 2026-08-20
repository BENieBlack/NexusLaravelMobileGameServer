<?php

namespace App\Models\Trx;

use App\Traits\CompositePrimaryKeyTrait;
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
    use CompositePrimaryKeyTrait;

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
    /** @var list<string> */
    protected array $uniqueKeys = ['sys_player_id', 'platform'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'platform',
        'paid_amount',
        'free_amount',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'sys_player_id' => 'integer',
        'paid_amount' => 'integer',
        'free_amount' => 'integer',
    ];

    /**
     * trx_playerとのリレーション
     */
    /**
     * @return BelongsTo<TrxPlayer, $this>
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
