<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\_BaseRequest;

class VersionRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     */
    public function authorize(): bool
    {
        // Access-Tokenの検証はミドルウェアで実施
        return true;
    }

    /**
     * バリデーションルール
     */
    public function rules(): array
    {
        return [
            'deploy_version' => 'nullable|integer',
        ];
    }

    /**
     * Deploy-Versionを取得（ヘッダーまたはボディ）
     * sys_deploy.idに該当
     */
    public function getDeployVersion(): ?int
    {
        // ボディから取得を優先、なければヘッダーから取得
        $version = $this->input('deploy_version') ?? $this->headers->get('Deploy-Version');

        return $version !== null ? (int) $version : null;
    }

    /**
     * バリデーション用の入力データを準備
     */
    protected function prepareForValidation(): void
    {
        // ボディで deploy_version が渡されていない場合、ヘッダーから取得
        if (! $this->has('deploy_version') && $this->headers->has('Deploy-Version')) {
            $this->merge([
                'deploy_version' => $this->headers->get('Deploy-Version'),
            ]);
        }
    }

    /**
     * カスタムバリデーションメッセージ
     */
    public function messages(): array
    {
        return [
            'deploy_version.integer' => 'Deploy version must be an integer',
        ];
    }

    /**
     * バリデーション属性名のカスタマイズ
     */
    public function attributes(): array
    {
        return [
            'deploy_version' => 'Deploy Version',
        ];
    }
}
