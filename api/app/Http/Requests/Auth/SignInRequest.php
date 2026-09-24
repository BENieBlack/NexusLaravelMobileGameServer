<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\_BaseRequest;

class SignInRequest extends _BaseRequest
{
    /**
     * リクエストの認可を判定
     */
    public function authorize(): bool
    {
        // サインインは誰でも可能
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
            'device_info' => ['required', 'array'],
            'device_info.os' => ['required', 'string', 'max:50'],
            'device_info.os_version' => ['required', 'string', 'max:50'],
            'device_info.model' => ['required', 'string', 'max:100'],
            'device_info.app_version' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * device_idを取得
     */
    public function getDeviceId(): string
    {
        return $this->input('device_id');
    }

    /**
     * device_infoを取得
     *
     * @return array<string, mixed>
     */
    public function getDeviceInfo(): array
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
            'device_info.required' => 'device_info is required',
            'device_info.array' => 'device_info must be an array',
            'device_info.os.required' => 'os is required in device_info',
            'device_info.os_version.required' => 'os_version is required in device_info',
            'device_info.model.required' => 'model is required in device_info',
            'device_info.app_version.required' => 'app_version is required in device_info',
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
        ];
    }
}
