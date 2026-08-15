<?php

namespace App\Models\Trx;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TrxWalletBalance Model
 *
 * 通貨残高を取得単位で管理するモデル（FIFO方式 + 有効期限管理 + 有償/無償管理）
 * PRIMARY KEY: id
 *
 * FIFO優先順位（有償を優先的に消費）:
 * 1. is_paid DESC (有償から消費)
 * 2. expire_at ASC (有効期限が近いものから、NULLは最後)
 * 3. id ASC (古い取得から)
 *
 * mst_item_id: 通貨アイテムID (string型: "gold", "event_coin"等)
 * is_paid: 有償フラグ (true=有償、false=無償)
 * current_amount: 現在の残数
 * initial_amount: 取得時の数
 * expire_at: 有効期限 (NULLの場合は無期限)
 *
 * @property int $current_amount
 * @property int $initial_amount
 * @property bool $is_paid
 */
class TrxWalletBalance extends _BaseTrx
{
    protected $table = 'trx_wallet_balance';

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（IDで一意）
     */
    /** @var list<string> */
    protected array $uniqueKeys = ['id'];

    /** @var list<string> */
    protected $fillable = [
        'sys_player_id',
        'mst_item_id',
        'is_paid',
        'current_amount',
        'initial_amount',
        'expire_at',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'id' => 'integer',
        'sys_player_id' => 'integer',
        'mst_item_id' => 'string',
        'is_paid' => 'boolean',
        'current_amount' => 'integer',
        'initial_amount' => 'integer',
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
     * レスポンス用配列に変換
     *
     * データベース層の'id'をAPI層の'trx_wallet_balance_id'に変換
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        if (isset($array['id'])) {
            $array['trx_wallet_balance_id'] = $array['id'];
            unset($array['id']);
        }

        return $array;
    }

    // ===== Getter Methods =====

    /**
     * 残高IDを取得
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * 有償フラグを取得
     */
    public function getIsPaid(): bool
    {
        return $this->is_paid;
    }

    /**
     * 現在の残高を取得
     */
    public function getCurrentAmount(): int
    {
        return $this->current_amount;
    }

    /**
     * 初期取得数量を取得
     */
    public function getInitialAmount(): int
    {
        return $this->initial_amount;
    }

    /**
     * 有効期限を取得（無期限の場合はnull）
     */
    public function getExpireAt(): ?CarbonImmutable
    {
        return $this->getDateAttribute('expire_at');
    }

    // ===== Setter Methods =====

    /**
     * 有償フラグを設定
     */
    public function setIsPaid(bool $isPaid): void
    {
        $this->setAttribute('is_paid', $isPaid);
    }

    /**
     * 現在の残高を設定
     */
    public function setCurrentAmount(int $currentAmount): void
    {
        $this->setAttribute('current_amount', $currentAmount);
    }

    /**
     * システムプレイヤーIDを設定
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * マスターアイテムIDを設定
     */
    public function setMstItemId(string $mstItemId): void
    {
        $this->setAttribute('mst_item_id', $mstItemId);
    }

    /**
     * 初期取得数量を設定
     */
    public function setInitialAmount(int $initialAmount): void
    {
        $this->setAttribute('initial_amount', $initialAmount);
    }

    /**
     * 有効期限を設定
     */
    public function setExpireAt(?CarbonImmutable $expireAt): void
    {
        $this->setAttribute('expire_at', $expireAt);
    }
}
