<?php

namespace App\Http\Requests\Mailbox;

use App\Http\Requests\_BaseRequest;

/**
 * LockRequest
 *
 * メールロックリクエスト
 */
class LockRequest extends _BaseRequest
{
    /**
     * バリデーションルール
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'trx_mailbox_id' => ['required', 'integer', 'min:1'],
            'is_locked' => ['required', 'boolean'],
        ];
    }

    /**
     * メールIDを取得
     *
     * @return int
     */
    public function getTrxMailboxId(): int
    {
        return (int)$this->input('trx_mailbox_id');
    }

    /**
     * ロックフラグを取得
     *
     * @return bool
     */
    public function getIsLocked(): bool
    {
        return (bool)$this->input('is_locked');
    }
}
