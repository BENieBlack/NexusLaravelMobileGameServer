<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\_BaseRequest;

/**
 * LoginRequest
 * 
 * ログインリクエスト
 * 認証済みのプレイヤーがログイン処理を実行
 */
class LoginRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // 認証済みユーザーのみ可能
        return true;
    }

    /**
     * バリデーションルール
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // ログインリクエストにはパラメータ不要
        // プレイヤーIDはApiSessionから取得
        return [];
    }
}
