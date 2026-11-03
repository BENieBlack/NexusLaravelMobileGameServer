<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\_BaseRequest;

/**
 * TransferByCodeRequest
 *
 * POST /api/account/transfer_code/transfer のリクエスト
 */
class TransferByCodeRequest extends _BaseRequest
{
    use TransferDeviceRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transfer_code' => ['required', 'string', 'alpha_num:ascii', 'max:32'],
            'password' => ['required', 'string', 'max:64'],
            ...$this->deviceRules(),
        ];
    }

    public function getTransferCode(): string
    {
        return (string) $this->input('transfer_code');
    }

    public function getPassword(): string
    {
        return (string) $this->input('password');
    }
}
