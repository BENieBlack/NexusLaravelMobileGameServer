<?php

namespace NexusMission\Exceptions;

use RuntimeException;

/**
 * MissionException
 *
 * ミッションのドメイン例外
 *
 * パッケージはゲーム固有のエラーコード体系を知らないため、種類だけを持つ。
 * クライアントへ返すコードへの変換はアプリケーション層の
 * MissionExceptionTranslator が行う。
 */
class MissionException extends RuntimeException
{
    private function __construct(
        private readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public static function missionNotFound(string $mstMissionId): self
    {
        return new self('MISSION_NOT_FOUND', "ミッションが見つかりません: {$mstMissionId}");
    }

    public static function missionNotOpen(string $mstMissionId): self
    {
        return new self('MISSION_NOT_OPEN', "ミッションの期間外です: {$mstMissionId}");
    }

    public static function notCompleted(string $mstMissionId, int $targetValue, int $progressValue): self
    {
        return new self(
            'MISSION_NOT_COMPLETED',
            "ミッションが未達成です: {$mstMissionId} 必要: {$targetValue}, 現在: {$progressValue}",
        );
    }

    public static function alreadyReceived(string $mstMissionId): self
    {
        return new self('MISSION_ALREADY_RECEIVED', "既に受け取り済みです: {$mstMissionId}");
    }
}
