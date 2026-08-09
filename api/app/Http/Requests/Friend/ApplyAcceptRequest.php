<?php

namespace App\Http\Requests\Friend;

use App\Http\Requests\_BaseRequest;

class ApplyAcceptRequest extends _BaseRequest
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
            'sys_friend_apply_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * sys_friend_apply_idを取得
     */
    public function getSysFriendApplyId(): int
    {
        return (int) $this->input('sys_friend_apply_id');
    }

    /**
     * 認証済みプレイヤーIDを取得
     *
     * ミドルウェアで設定された値を取得
     */
    public function getAuthenticatedPlayerId(): ?int
    {
        $playerId = $this->input('authenticated_player_id');

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
            'sys_friend_apply_id.required' => 'sys_friend_apply_id is required',
            'sys_friend_apply_id.integer' => 'sys_friend_apply_id must be an integer',
            'sys_friend_apply_id.min' => 'sys_friend_apply_id must be at least 1',
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
            'sys_friend_apply_id' => 'Friend Apply ID',
        ];
    }
}
