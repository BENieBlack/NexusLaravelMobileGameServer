<?php

namespace App\Domain\Mission\Responses;

use App\Domain\_BaseResponse;
use App\Domain\ReturnsUpdatedResources;
use App\Traits\HasUpdatedResources;
use NexusMission\ValueObjects\MissionStatus;

/**
 * ReceiveResponse
 *
 * ミッション報酬受け取りレスポンス
 *
 * 受け取り後のミッションの状態（配った報酬を含む）を返す
 */
class ReceiveResponse extends _BaseResponse implements ReturnsUpdatedResources
{
    use HasUpdatedResources;

    public function __construct(
        private readonly MissionStatus $missionStatus,
    ) {}

    /**
     * UseCaseの結果からレスポンスを生成
     */
    public static function from(MissionStatus $missionStatus): self
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
            'updated_resources' => $this->getUpdatedResources()->toResponseArray(),
        ];
    }
}
