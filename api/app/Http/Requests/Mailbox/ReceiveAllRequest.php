<?php

namespace App\Http\Requests\Mailbox;

use App\Domain\MailBox\Constants\Category;
use App\Http\Requests\_BaseRequest;
use Illuminate\Validation\Rule;

/**
 * ReceiveAllRequest
 *
 * 一括受取リクエスト
 */
class ReceiveAllRequest extends _BaseRequest
{
    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        return [
            'trx_mailbox_ids' => ['nullable', 'array'],
            'trx_mailbox_ids.*' => ['integer', 'min:1'],
            'category' => ['nullable', 'string', Rule::in(Category::all())],
        ];
    }

    /**
     * メールIDリストを取得
     *
     * @return array<int>|null
     */
    public function getTrxMailboxIds(): ?array
    {
        return $this->input('trx_mailbox_ids');
    }

    /**
     * カテゴリを取得
     */
    public function getCategory(): ?string
    {
        return $this->input('category');
    }
}
