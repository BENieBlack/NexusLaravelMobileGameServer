<?php

namespace App\Models\Sys;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SysFriendApply Model
 *
 * フレンド申請情報を管理するモデル
 */
class SysFriendApply extends _BaseSys
{
    /**
     * テーブル名
     */
    protected $table = 'sys_friend_apply';

    /**
     * 複数代入可能な属性
     *
     * @var array
     */
    protected $fillable = [
        'sender_sys_player_id',
        'receiver_sys_player_id',
        'status',
    ];

    /**
     * キャストする属性
     *
     * @var array
     */
    protected $casts = [
        'sender_sys_player_id' => 'integer',
        'receiver_sys_player_id' => 'integer',
    ];

    /**
     * ステータス定数
     */
    public const STATUS_APPLIED = 'Applied';

    public const STATUS_ACCEPTED = 'Accepted';

    public const STATUS_REJECTED = 'Rejected';

    public const STATUS_DELETED = 'Deleted';

    /**
     * 申請送信者
     */
    public function sendPlayer(): BelongsTo
    {
        return $this->belongsTo(SysPlayer::class, 'sender_sys_player_id');
    }

    /**
     * 申請受信者
     */
    public function receivePlayer(): BelongsTo
    {
        return $this->belongsTo(SysPlayer::class, 'receiver_sys_player_id');
    }

    /**
     * IDを取得
     */
    public function getId(): int
    {
        return $this->getAttribute('id');
    }

    /**
     * 送信者プレイヤーIDを取得
     */
    public function getSenderSysPlayerId(): int
    {
        return $this->getAttribute('sender_sys_player_id');
    }

    /**
     * 受信者プレイヤーIDを取得
     */
    public function getReceiverSysPlayerId(): int
    {
        return $this->getAttribute('receiver_sys_player_id');
    }

    /**
     * ステータスを取得
     */
    public function getStatus(): string
    {
        return $this->getAttribute('status');
    }

    /**
     * 送信者プレイヤーIDを設定
     */
    public function setSenderSysPlayerId(int $senderSysPlayerId): void
    {
        $this->setAttribute('sender_sys_player_id', $senderSysPlayerId);
    }

    /**
     * 受信者プレイヤーIDを設定
     */
    public function setReceiverSysPlayerId(int $receiverSysPlayerId): void
    {
        $this->setAttribute('receiver_sys_player_id', $receiverSysPlayerId);
    }

    /**
     * ステータスを設定
     */
    public function setStatus(string $status): void
    {
        $this->setAttribute('status', $status);
    }

    /**
     * 利用可能なステータス一覧を取得
     */
    public static function availableStatuses(): array
    {
        return [
            self::STATUS_APPLIED,
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_DELETED,
        ];
    }

    /**
     * 申請中かチェック
     */
    public function isApplied(): bool
    {
        return $this->getStatus() === self::STATUS_APPLIED;
    }

    /**
     * 承認済みかチェック
     */
    public function isAccepted(): bool
    {
        return $this->getStatus() === self::STATUS_ACCEPTED;
    }

    /**
     * 却下済みかチェック
     */
    public function isRejected(): bool
    {
        return $this->getStatus() === self::STATUS_REJECTED;
    }

    /**
     * 削除済みかチェック
     */
    public function isDeleted(): bool
    {
        return $this->getStatus() === self::STATUS_DELETED;
    }

    /**
     * フレンド申請を承認
     *
     * 状態を変更するだけでDBには反映しない。
     * 永続化はRepositoryのsetModel()経由で行うこと。
     */
    public function accept(): void
    {
        $this->status = self::STATUS_ACCEPTED;
    }

    /**
     * フレンド申請を却下
     *
     * 状態を変更するだけでDBには反映しない。
     * 永続化はRepositoryのsetModel()経由で行うこと。
     */
    public function reject(): void
    {
        $this->status = self::STATUS_REJECTED;
    }

    /**
     * フレンド申請を削除
     *
     * 状態を変更するだけでDBには反映しない。
     * 永続化はRepositoryのsetModel()経由で行うこと。
     */
    public function markAsDeleted(): void
    {
        $this->status = self::STATUS_DELETED;
    }

    /**
     * APIレスポンス用の配列に変換
     * id を sys_friend_apply_id に変換
     *
     * @return array<string, mixed>
     */
    public function toResponseArray(): array
    {
        $array = parent::toResponseArray();

        // id を sys_friend_apply_id にリネーム
        if (isset($array['id'])) {
            $array['sys_friend_apply_id'] = $array['id'];
            unset($array['id']);
        }

        return $array;
    }
}
