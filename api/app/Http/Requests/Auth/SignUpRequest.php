<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\_BaseRequest;

class SignUpRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     *
     * @return bool
     */
    public function authorize(): bool
    {
        // サインアップは誰でも可能
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
            'device_id' => ['required', 'string', 'max:255'],
            'device_info' => ['nullable', 'array'],
            'device_info.os' => ['nullable', 'string', 'max:50'],
            'device_info.os_version' => ['nullable', 'string', 'max:50'],
            'device_info.model' => ['nullable', 'string', 'max:100'],
            'device_info.app_version' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * device_idを取得
     *
     * @return string
     */
    public function getDeviceId(): string
    {
        return $this->input('device_id');
    }

    /**
     * device_infoを取得
     *
     * @return array<string, mixed>|null
     */
    public function getDeviceInfo(): ?array
    {
        return $this->input('device_info');
    }

    /**
     * カスタムバリデーションメッセージ
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'device_id.required' => 'device_id is required',
            'device_id.string' => 'device_id must be a string',
            'device_id.max' => 'device_id must not exceed 255 characters',
            'device_info.array' => 'device_info must be an array',
            'device_info.os.string' => 'device_info.os must be a string',
            'device_info.os_version.string' => 'device_info.os_version must be a string',
            'device_info.model.string' => 'device_info.model must be a string',
            'device_info.app_version.string' => 'device_info.app_version must be a string',
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
            'device_id' => 'Device ID',
            'device_info' => 'Device Info',
            'device_info.os' => 'OS',
            'device_info.os_version' => 'OS Version',
            'device_info.model' => 'Device Model',
            'device_info.app_version' => 'App Version',
        ];
    }
}
