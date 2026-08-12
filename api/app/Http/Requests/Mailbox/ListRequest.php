<?php

namespace App\Http\Requests\Mailbox;

use App\Domain\Mailbox\Constants\Category;
use App\Domain\Mailbox\Constants\Priority;
use App\Http\Requests\_BaseRequest;
use Illuminate\Validation\Rule;

/**
 * ListRequest
 *
 * メールボックス一覧取得リクエスト
 */
class ListRequest extends _BaseRequest
{
    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', Rule::in(Category::all())],
            'priority' => ['nullable', 'string', Rule::in(array_column(Priority::cases(), 'value'))],
            'only_unread' => ['nullable', 'boolean'],
            'only_locked' => ['nullable', 'boolean'],
        ];
    }

    /**
     * カテゴリを取得
     */
    public function getCategory(): ?string
    {
        return $this->input('category');
    }

    /**
     * 優先度を取得
     */
    public function getPriority(): ?string
    {
        return $this->input('priority');
    }

    /**
     * 未読のみフラグを取得
     */
    public function getOnlyUnread(): bool
    {
        return (bool) $this->input('only_unread', false);
    }

    /**
     * ロックのみフラグを取得
     */
    public function getOnlyLocked(): bool
    {
        return (bool) $this->input('only_locked', false);
    }
}
