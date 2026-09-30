<?php

namespace App\Http\Requests\Item;

use App\Http\Requests\_BaseRequest;

/**
 * UseRequest
 *
 * アイテム使用APIのリクエスト
 */
class UseRequest extends _BaseRequest
{
    /**
     * バリデーションルール
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mst_item_id' => ['required', 'string', 'max:255'],
            'use_count' => ['required', 'integer', 'min:1', 'max:9999'],
        ];
    }

    /**
     * カスタムバリデーションメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'mst_item_id.required' => 'アイテムIDは必須です',
            'mst_item_id.string' => 'アイテムIDは文字列である必要があります',
            'use_count.required' => '使用個数は必須です',
            'use_count.integer' => '使用個数は整数である必要があります',
            'use_count.min' => '使用個数は1以上である必要があります',
            'use_count.max' => '使用個数は9999以下である必要があります',
        ];
    }

    /**
     * マスターアイテムIDを取得
     *
     * @return string mst_item.id（マスター定義アイテム）
     */
    public function getMstItemId(): string
    {
        return (string) $this->input('mst_item_id');
    }

    /**
     * 使用個数を取得
     */
    public function getUseCount(): int
    {
        return (int) $this->input('use_count');
    }
}
