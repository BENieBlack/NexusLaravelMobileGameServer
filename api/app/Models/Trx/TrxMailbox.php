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
 * @property bool $is_protected
 * @property \Carbon\Carbon|null $expires_at
 * @property \Carbon\Carbon|null $read_at
 * @property \Carbon\Carbon|null $received_at
 * @property string|null $sender_name
 * @property array|null $custom_params
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
        'is_protected',
        'expires_at',
        'read_at',
        'received_at',
        'sender_name',
        'custom_params',
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
        'is_protected' => 'boolean',
        'expires_at' => 'datetime',
        'read_at' => 'datetime',
        'received_at' => 'datetime',
        'custom_params' => 'array',
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
     * 保護フラグを取得
     *
     * @return bool
     */
    public function getIsProtected(): bool
    {
        return (bool)$this->getAttribute('is_protected');
    }

    /**
     * 有効期限を取得
     *
     * @return \Carbon\Carbon|null
     */
    public function getExpiresAt(): ?\Carbon\Carbon
    {
        return $this->getAttribute('expires_at');
    }

    /**
     * 既読日時を取得
     *
     * @return \Carbon\Carbon|null
     */
    public function getReadAt(): ?\Carbon\Carbon
    {
        return $this->getAttribute('read_at');
    }

    /**
     * 受取日時を取得
     *
     * @return \Carbon\Carbon|null
     */
    public function getReceivedAt(): ?\Carbon\Carbon
    {
        return $this->getAttribute('received_at');
    }

    /**
     * 送信者名を取得
     *
     * @return string|null
     */
    public function getSenderName(): ?string
    {
        return $this->getAttribute('sender_name');
    }

    /**
     * カスタムパラメータを取得
     *
     * @return array|null
     */
    public function getCustomParams(): ?array
    {
        return $this->getAttribute('custom_params');
    }

    /**
     * メールが期限切れかどうか
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        $expiresAt = $this->getExpiresAt();
        return $expiresAt !== null && $expiresAt->isPast();
    }

    /**
     * メールが未読かどうか
     *
     * @return bool
     */
    public function isUnread(): bool
    {
        return $this->getReadAt() === null;
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
     * 保護フラグを設定
     *
     * @param bool $isProtected
     * @return void
     */
    public function setIsProtected(bool $isProtected): void
    {
        $this->setAttribute('is_protected', $isProtected);
    }

    /**
     * 有効期限を設定
     *
     * @param \Carbon\Carbon|null $expiresAt
     * @return void
     */
    public function setExpiresAt(?\Carbon\Carbon $expiresAt): void
    {
        $this->setAttribute('expires_at', $expiresAt);
    }

    /**
     * 既読日時を設定
     *
     * @param \Carbon\Carbon|null $readAt
     * @return void
     */
    public function setReadAt(?\Carbon\Carbon $readAt): void
    {
        $this->setAttribute('read_at', $readAt);
    }

    /**
     * 受取日時を設定
     *
     * @param \Carbon\Carbon|null $receivedAt
     * @return void
     */
    public function setReceivedAt(?\Carbon\Carbon $receivedAt): void
    {
        $this->setAttribute('received_at', $receivedAt);
    }

    /**
     * 送信者名を設定
     *
     * @param string|null $senderName
     * @return void
     */
    public function setSenderName(?string $senderName): void
    {
        $this->setAttribute('sender_name', $senderName);
    }

    /**
     * カスタムパラメータを設定
     *
     * @param array|null $customParams
     * @return void
     */
    public function setCustomParams(?array $customParams): void
    {
        $this->setAttribute('custom_params', $customParams);
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
