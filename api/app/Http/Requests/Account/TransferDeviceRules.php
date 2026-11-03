<?php

namespace App\Http\Requests\Account;

/**
 * TransferDeviceRules
 *
 * 引き継ぎ先の端末の入力（sign_in と同じ形）
 */
trait TransferDeviceRules
{
    /**
     * @return array<string, mixed>
     */
    protected function deviceRules(): array
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

    public function getDeviceId(): string
    {
        return (string) $this->input('device_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function getDeviceInfo(): array
    {
        return $this->input('device_info');
    }
}
