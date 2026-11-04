<?php

namespace App\Domain\Mission\Responses;

use App\Domain\_BaseResponse;
use NexusMission\ValueObjects\MissionStatus;

/**
 * ListResponse
 *
 * ミッション一覧レスポンス
 *
 * 期間内のミッションの進捗・受け取り状況・報酬を返す。
 * 名前や説明はクライアントがマスターデータ（mst_mission__l10n）から引く。
 */
class ListResponse extends _BaseResponse
{
    /**
     * @param  array<int, MissionStatus>  $missionStatuses
     */
    public function __construct(
        private readonly array $missionStatuses,
    ) {}

    /**
     * UseCaseの結果からレスポンスを生成
     *
     * @param  array<int, MissionStatus>  $missionStatuses
     */
    public static function from(array $missionStatuses): self
    {
        return new self($missionStatuses);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mission_list' => array_map(
                fn (MissionStatus $missionStatus) => $missionStatus->toArray(),
                $this->missionStatuses,
            ),
        ];
    }
}
