<?php

namespace App\Http\Requests;

/**
 * _BaseRequestInterface
 *
 * すべてのリクエストクラスが実装すべきインターフェース
 */
interface _BaseRequestInterface
{
    /**
     * リクエストの認可を判定
     */
    public function authorize(): bool;

    /**
     * バリデーションルール
     *
     * @return array<string, mixed>
     */
    public function rules(): array;

    /**
     * カスタムバリデーションメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array;

    /**
     * バリデーション属性名のカスタマイズ
     *
     * @return array<string, string>
     */
    public function attributes(): array;
}
