<?php

namespace App\Http\Requests\Account;

use App\Http\Requests\_BaseRequest;

/**
 * IssueTransferCodeRequest
 *
 * POST /api/account/transfer_code/issue のリクエスト
 */
class IssueTransferCodeRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 空白を含まない表示可能なASCII文字
            'password' => ['required', 'string', 'min:8', 'max:64', 'regex:/^[\x21-\x7E]+$/'],
        ];
    }

    public function getPassword(): string
    {
        return (string) $this->input('password');
    }
}
