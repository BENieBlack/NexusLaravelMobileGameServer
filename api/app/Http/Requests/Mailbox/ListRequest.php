<?php

namespace App\Http\Requests\Mailbox;

use App\Http\Requests\_BaseRequest;

/**
 * ListRequest
 *
 * メールボックス一覧取得リクエスト
 */
class ListRequest extends _BaseRequest
{
    /**
     * バリデーションルール
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            // プレイヤーIDはApiSessionから自動取得
        ];
    }
}
