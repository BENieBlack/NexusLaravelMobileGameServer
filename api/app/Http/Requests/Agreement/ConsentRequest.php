<?php

namespace App\Http\Requests\Agreement;

use App\Http\Requests\_BaseRequest;

/**
 * ConsentRequest
 *
 * POST /api/agreement/consent のリクエスト
 */
class ConsentRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sys_agreement_id' => ['required', 'integer', 'min:1'],
        ];
    }

    public function getSysAgreementId(): int
    {
        return (int) $this->input('sys_agreement_id');
    }
}
