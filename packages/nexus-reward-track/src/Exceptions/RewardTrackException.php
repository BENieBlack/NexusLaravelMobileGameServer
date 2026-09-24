<?php

namespace NexusRewardTrack\Exceptions;

use RuntimeException;

/**
 * RewardTrackException
 *
 * RewardTrackのドメイン例外
 *
 * パッケージはゲーム固有のエラーコード体系を知らないため、種類だけを持つ。
 * クライアントへ返すコードへの変換はアプリケーション層の
 * RewardTrackExceptionTranslator が行う。
 */
class RewardTrackException extends RuntimeException
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

    public static function trackNotFound(string $trackId): self
    {
        return new self('REWARD_TRACK_NOT_FOUND', "RewardTrack が見つかりません: {$trackId}");
    }

    public static function trackNotStarted(string $trackId): self
    {
        return new self('REWARD_TRACK_NOT_STARTED', "トラックはまだ開始していません: {$trackId}");
    }

    public static function trackEnded(string $trackId): self
    {
        return new self('REWARD_TRACK_ENDED', "トラックは終了しています: {$trackId}");
    }

    public static function lineNotFound(string $lineId): self
    {
        return new self('REWARD_TRACK_LINE_NOT_FOUND', "RewardTrackLine が見つかりません: {$lineId}");
    }

    public static function freeLineNotPurchasable(string $lineId): self
    {
        return new self('REWARD_TRACK_FREE_LINE_NOT_PURCHASABLE', "無料ラインは購入できません: {$lineId}");
    }

    public static function lineAlreadyOwned(string $lineId): self
    {
        return new self('REWARD_TRACK_LINE_ALREADY_OWNED', "既に購入済みのラインです: {$lineId}");
    }

    public static function lineNotOwned(string $lineId): self
    {
        return new self('REWARD_TRACK_LINE_NOT_OWNED', "このラインは購入していません: {$lineId}");
    }

    public static function milestoneNotFound(string $milestoneId): self
    {
        return new self('REWARD_TRACK_MILESTONE_NOT_FOUND', "マイルストーンが見つかりません: {$milestoneId}");
    }

    public static function progressNotEnough(int $required, int $current): self
    {
        return new self(
            'REWARD_TRACK_PROGRESS_NOT_ENOUGH',
            "進捗が不足しています。必要: {$required}, 現在: {$current}",
        );
    }

    public static function alreadyReceived(string $milestoneId, string $lineId): self
    {
        return new self('REWARD_TRACK_ALREADY_RECEIVED', "既に受け取り済みです: {$milestoneId} / {$lineId}");
    }
}
