<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * _BaseRequest
 *
 * すべてのリクエストクラスの基底クラス
 * 共通のバリデーション処理やヘルパーメソッドを提供
 */
abstract class _BaseRequest extends FormRequest implements _BaseRequestInterface
{
    /**
     * リクエストの認可を判定
     *
     * デフォルトではtrueを返す（各リクエストクラスでオーバーライド可能）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルール
     *
     * 各リクエストクラスで必ずオーバーライドすること
     *
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    /**
     * カスタムバリデーションメッセージ
     *
     * 必要に応じて各リクエストクラスでオーバーライド
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * バリデーション属性名のカスタマイズ
     *
     * 必要に応じて各リクエストクラスでオーバーライド
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * X-Unique-Request-Identifierヘッダーを取得
     *
     * 冪等性保証のためのユニークリクエスト識別子
     */
    public function getUniqueRequestIdentifier(): ?string
    {
        return $this->headers->get('X-Unique-Request-Identifier');
    }

    /**
     * 認証済みプレイヤーIDを取得
     *
     * VerifyAccessTokenミドルウェアで設定される
     */
    public function resolveAuthenticatedPlayerId(): ?int
    {
        return $this->attributes->get('authenticated_player_id');
    }
}
