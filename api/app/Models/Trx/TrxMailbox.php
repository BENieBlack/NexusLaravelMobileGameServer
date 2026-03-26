<?php

namespace App\Models\Trx;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Mst\MstMailbox;

/**
 * TrxMailbox Model
 * 
 * プレイヤーのメールボックスデータを管理するモデル
 * 
 * @property int $id
 * @property int $sys_player_id
 * @property string $mst_mailbox_id
 * @property bool $is_opened
 * @property bool $is_received
 * @property bool $is_delete
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class TrxMailbox extends _BaseTrx
{
    protected $table = 'trx_mailbox';

    /**
     * SELECTキー（プレイヤーIDでSELECT）
     * 
     * @var string
     */
    protected string $selectKey = 'sys_player_id';

    /**
     * ユニークキー（IDで一意）
     * 
     * @var array<int, string>
     */
    protected array $uniqueKeys = ['id'];

    /** @var array<int, string> */
    protected $fillable = [
        'sys_player_id',
        'mst_mailbox_id',
        'is_opened',
        'is_received',
        'is_delete',
        'created_at',
        'updated_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'sys_player_id' => 'integer',
        'is_opened' => 'boolean',
        'is_received' => 'boolean',
        'is_delete' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * trx_playerとのリレーション
     *
     * @return BelongsTo
     */
    public function trxPlayer(): BelongsTo
    {
        return $this->belongsTo(TrxPlayer::class, 'sys_player_id', 'sys_player_id');
    }

    /**
     * mst_mailboxとのリレーション
     *
     * @return BelongsTo
     */
    public function mstMailbox(): BelongsTo
    {
        return $this->belongsTo(MstMailbox::class, 'mst_mailbox_id', 'id');
    }

    /**
     * メールボックスIDを取得
     *
     * @return int
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * プレイヤーIDを取得
     *
     * @return int
     */
    public function getSysPlayerId(): int
    {
        return $this->getAttribute('sys_player_id');
    }

    /**
     * マスターメールボックスIDを取得
     *
     * @return string
     */
    public function getMstMailboxId(): string
    {
        return $this->getAttribute('mst_mailbox_id');
    }

    /**
     * 開封フラグを取得
     *
     * @return bool
     */
    public function getIsOpened(): bool
    {
        return (bool)$this->getAttribute('is_opened');
    }

    /**
     * 受取フラグを取得
     *
     * @return bool
     */
    public function getIsReceived(): bool
    {
        return (bool)$this->getAttribute('is_received');
    }

    /**
     * 削除フラグを取得
     *
     * @return bool
     */
    public function getIsDelete(): bool
    {
        return (bool)$this->getAttribute('is_delete');
    }

    /**
     * プレイヤーIDを設定
     *
     * @param int $sysPlayerId
     * @return void
     */
    public function setSysPlayerId(int $sysPlayerId): void
    {
        $this->setAttribute('sys_player_id', $sysPlayerId);
    }

    /**
     * マスターメールボックスIDを設定
     *
     * @param string $mstMailboxId
     * @return void
     */
    public function setMstMailboxId(string $mstMailboxId): void
    {
        $this->setAttribute('mst_mailbox_id', $mstMailboxId);
    }

    /**
     * 開封フラグを設定
     *
     * @param bool $isOpened
     * @return void
     */
    public function setIsOpened(bool $isOpened): void
    {
        $this->setAttribute('is_opened', $isOpened);
    }

    /**
     * 受取フラグを設定
     *
     * @param bool $isReceived
     * @return void
     */
    public function setIsReceived(bool $isReceived): void
    {
        $this->setAttribute('is_received', $isReceived);
    }

    /**
     * 削除フラグを設定
     *
     * @param bool $isDelete
     * @return void
     */
    public function setIsDelete(bool $isDelete): void
    {
        $this->setAttribute('is_delete', $isDelete);
    }

    /**
     * レスポンス用配列に変換
     * 
     * @return array
     */
    public function toResponseArray(): array
    {
        $array = $this->toArray();
        
        // IDカラムはtrx_mailbox_idとして返す
        if (isset($array['id'])) {
            $array['trx_mailbox_id'] = $array['id'];
            unset($array['id']);
        }
        
        return $array;
    }
}
