<?php

namespace App\Http\Requests\Account;

use App\Domain\Auth\Constants\ExternalProviderConst;
use App\Http\Requests\_BaseRequest;
use Illuminate\Validation\Rule;

/**
 * LinkExternalAccountRequest
 *
 * POST /api/account/external/link のリクエスト
 */
class LinkExternalAccountRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(ExternalProviderConst::ALL)],
            'id_token' => ['required', 'string', 'max:4096'],
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
