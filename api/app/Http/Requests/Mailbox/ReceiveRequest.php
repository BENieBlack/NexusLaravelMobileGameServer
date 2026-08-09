<?php

namespace App\Http\Requests\Mailbox;

use App\Http\Requests\_BaseRequest;

/**
 * ReceiveRequest
 *
 * 添付配布物受取リクエスト
 */
class ReceiveRequest extends _BaseRequest
{
    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        return [
            'trx_mailbox_id' => ['required', 'integer'],
        ];
    }

    /**
     * メールボックスIDを取得
     */
    public function getTrxMailboxId(): int
    {
        return (int) $this->input('trx_mailbox_id');
    }
}
