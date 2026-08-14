<?php

namespace App\Http\Requests\Guild;

use App\Http\Requests\_BaseRequest;

class CreateRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     */
    public function authorize(): bool
    {
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
            'name' => ['required', 'string', 'min:1', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * ギルド名を取得
     */
    public function getName(): string
    {
        return $this->input('name');
    }

    /**
     * ギルド説明を取得
     */
    public function getDescription(): string
    {
        return $this->input('description', '');
    }

    /**
     * 認証済みプレイヤーIDを取得
     */
    public function resolveAuthenticatedPlayerId(): ?int
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
            'name.required' => 'Guild name is required',
            'name.string' => 'Guild name must be a string',
            'name.min' => 'Guild name must be at least 1 character',
            'name.max' => 'Guild name must not exceed 100 characters',
            'description.string' => 'Description must be a string',
            'description.max' => 'Description must not exceed 1000 characters',
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
            'name' => 'Guild Name',
            'description' => 'Description',
        ];
    }
}
