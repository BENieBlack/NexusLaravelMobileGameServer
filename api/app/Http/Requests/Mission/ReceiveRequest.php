<?php

namespace App\Http\Requests\Mission;

use App\Http\Requests\_BaseRequest;

class ReceiveRequest extends _BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mst_mission_id' => ['required', 'string', 'max:64'],
        ];
    }

    public function getMstMissionId(): string
    {
        return (string) $this->input('mst_mission_id');
    }
}
