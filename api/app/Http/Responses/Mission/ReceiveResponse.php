<?php

namespace App\Http\Responses\Mission;

use App\Http\Responses\_BaseResponse;
use NexusMission\ValueObjects\MissionStatus;

/**
 * ReceiveResponse
 *
 * ミッション報酬受け取りレスポンス
 *
 * 受け取り後のミッションの状態（配った報酬を含む）を返す
 */
class ReceiveResponse extends _BaseResponse
{
    public function __construct(
        private readonly MissionStatus $missionStatus,
    ) {}

    /**
     * UseCaseの結果からレスポンスを生成
     */
    public static function fromResult(MissionStatus $missionStatus): self
    {
        return new self($missionStatus);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mission' => $this->missionStatus->toArray(),
        ];
    }
}
