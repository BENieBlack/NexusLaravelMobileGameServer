<?php

namespace App\Http\Requests\Account;

use App\Domain\Auth\Constants\ExternalProviderConst;
use App\Http\Requests\_BaseRequest;
use Illuminate\Validation\Rule;

/**
 * TransferByExternalAccountRequest
 *
 * POST /api/account/external/transfer のリクエスト
 */
class TransferByExternalAccountRequest extends _BaseRequest
{
    use TransferDeviceRules;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(ExternalProviderConst::ALL)],
            'id_token' => ['required', 'string', 'max:4096'],
            ...$this->deviceRules(),
        ];
    }

    public function getProvider(): string
    {
        return (string) $this->input('provider');
    }

    public function getIdToken(): string
    {
        return (string) $this->input('id_token');
    }
}
