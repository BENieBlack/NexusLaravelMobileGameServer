<?php

namespace App\Http\Requests\Mailbox;

use App\Http\Requests\_BaseRequest;

/**
 * OpenRequest
 *
 * メール既読リクエスト
 */
class OpenRequest extends _BaseRequest
{
    /**
     * バリデーションルール
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'trx_mailbox_id' => ['required', 'integer'],
        ];
    }

    /**
     * メールボックスIDを取得
     *
     * @return int
     */
    public function getTrxMailboxId(): int
    {
        return (int)$this->input('trx_mailbox_id');
    }
}
