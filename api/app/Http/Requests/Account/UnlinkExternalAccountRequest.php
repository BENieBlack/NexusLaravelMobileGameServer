<?php

namespace App\Http\Requests\Account;

use App\Domain\Auth\Constants\ExternalProviderConst;
use App\Http\Requests\_BaseRequest;
use Illuminate\Validation\Rule;

/**
 * UnlinkExternalAccountRequest
 *
 * POST /api/account/external/unlink のリクエスト
 */
class UnlinkExternalAccountRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(ExternalProviderConst::ALL)],
        ];
    }

    public function getProvider(): string
    {
        return (string) $this->input('provider');
    }
}
