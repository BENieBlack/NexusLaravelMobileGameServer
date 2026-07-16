<?php

namespace App\Http\Requests\Mailbox;

use App\Http\Requests\_BaseRequest;

/**
 * ProtectRequest
 *
 * メール保護リクエスト
 */
class ProtectRequest extends _BaseRequest
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
            'is_protected' => ['required', 'boolean'],
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
     * 保護フラグを取得
     *
     * @return bool
     */
    public function getIsProtected(): bool
    {
        return (bool)$this->input('is_protected');
    }
}
