<?php

namespace App\Http\Requests\Friend;

use App\Http\Requests\_BaseRequest;

class ApplySendRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     */
    public function authorize(): bool
    {
        // 認証済みユーザーのみ
        return true;
    }

    /**
     * バリデーションルール
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'my_id' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * my_idを取得
     */
    public function getMyId(): string
    {
        return $this->input('my_id');
    }

    /**
     * 認証済みプレイヤーIDを取得
     *
     * ミドルウェアで設定された値を取得
     */
    public function getAuthenticatedPlayerId(): ?int
    {
        $playerId = $this->attributes->get('authenticated_player_id');

        return $playerId ? (int) $playerId : null;
    }

    /**
     * カスタムバリデーションメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'my_id.required' => 'my_id is required',
            'my_id.string' => 'my_id must be a string',
            'my_id.max' => 'my_id must not exceed 255 characters',
        ];
    }

    /**
     * バリデーション属性名のカスタマイズ
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'my_id' => 'My ID',
        ];
    }
}
