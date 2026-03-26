<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\_BaseRequest;

class VersionRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // Access-Tokenの検証は後でミドルウェアで実装
        return true;
    }

    /**
     * バリデーションルール
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            // HTTPヘッダーから取得するため、header経由でバリデーション
        ];
    }

    /**
     * Access-Tokenをヘッダーから取得
     *
     * @return string|null
     */
    public function getAccessToken(): ?string
    {
        return $this->headers->get('Access-Token');
    }

    /**
     * Client-Versionをヘッダーから取得
     *
     * @return string|null
     */
    public function getClientVersion(): ?string
    {
        return $this->headers->get('Client-Version');
    }

    /**
     * Deploy-Versionをヘッダーから取得
     * sys_deploy.idに該当
     *
     * @return int|null
     */
    public function getDeployVersion(): ?int
    {
        $version = $this->headers->get('Deploy-Version');
        return $version !== null ? (int) $version : null;
    }

    /**
     * カスタムバリデーションメッセージ
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'Access-Token.required' => 'Access-Token header is required',
            'Client-Version.required' => 'Client-Version header is required',
            'Deploy-Version.required' => 'Deploy-Version header is required',
            'Deploy-Version.integer' => 'Deploy-Version must be an integer',
        ];
    }

    /**
     * バリデーション用の入力データを準備
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // ヘッダーの値をバリデーション可能な形式にマージ
        $this->merge([
            'Access-Token' => $this->headers->get('Access-Token'),
            'Client-Version' => $this->headers->get('Client-Version'),
            'Deploy-Version' => $this->headers->get('Deploy-Version'),
        ]);
    }

    /**
     * バリデーション属性名のカスタマイズ
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'Access-Token' => 'Access Token',
            'Client-Version' => 'Client Version',
            'Deploy-Version' => 'Deploy Version',
        ];
    }
}
