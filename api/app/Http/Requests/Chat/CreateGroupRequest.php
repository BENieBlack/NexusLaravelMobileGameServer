<?php

namespace App\Http\Requests\Chat;

use App\Http\Requests\_BaseRequest;

class CreateGroupRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    public function getName(): string
    {
        return (string) $this->input('name');
    }
}
