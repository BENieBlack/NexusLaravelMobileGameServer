<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\_BaseRequest;

class RefreshTokenRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     */
    public function authorize(): bool
    {
        // リフレッシュトークンは誰でも可能
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
            'refresh_token' => ['required', 'string', 'size:64'],
        ];
    }

    /**
     * refresh_tokenを取得
     */
    public function getRefreshToken(): string
    {
        return $this->input('refresh_token');
    }

    /**
     * カスタムバリデーションメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'refresh_token.required' => 'refresh_token is required',
            'refresh_token.string' => 'refresh_token must be a string',
            'refresh_token.size' => 'refresh_token must be 64 characters',
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
            'refresh_token' => 'Refresh Token',
        ];
    }
}
