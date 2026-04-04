<?php

namespace App\Persistence;

use App\Utilities\Clock;
use Carbon\CarbonImmutable;

/**
 * ApiSession
 * 
 * APIコール時に確定したリクエストコンテキスト情報を保持する汎用クラス
 * リクエストスコープで管理され、Middlewareで初期化される
 * 
 * 管理している情報:
 * - sysPlayerId: 認証されたプレイヤーID
 * - now: リクエスト開始時の固定時刻（Clock::now()）
 * 
 * 使用例:
 * - Middleware: ApiSession::setSysPlayerId($sysPlayerId)
 * - Repository/Service/UseCase: 
 *   - $sysPlayerId = ApiSession::getSysPlayerId()
 *   - $now = ApiSession::getNow()
 * - インスタンス操作: app(ApiSession::class)->getPlayerId()
 */
class ApiSession
{
    /**
     * プレイヤーID
     *
     * @var int|null
     */
    private ?int $sysPlayerId = null;

    /**
     * リクエスト開始時刻（固定値）
     *
     * @var CarbonImmutable|null
     */
    private ?CarbonImmutable $now = null;

    /**
     * コンストラクタ
     *
     * @param int|null $sysPlayerId プレイヤーID（オプション）
     * @param CarbonImmutable|null $now リクエスト開始時刻（オプション）
     */
    public function __construct(?int $sysPlayerId = null, ?CarbonImmutable $now = null)
    {
        $this->sysPlayerId = $sysPlayerId;
        $this->now = $now;
    }

    /**
     * プレイヤーIDを設定（インスタンスメソッド）
     *
     * @param int $sysPlayerId プレイヤーID
     * @return void
     */
    public function setPlayerId(int $sysPlayerId): void
    {
        $this->sysPlayerId = $sysPlayerId;
    }

    /**
     * プレイヤーIDを取得（インスタンスメソッド）
     *
     * @return int プレイヤーID
     * @throws \RuntimeException プレイヤーIDが設定されていない場合
     */
    public function getPlayerId(): int
    {
        if ($this->sysPlayerId === null) {
            throw new \RuntimeException(
                'Player ID is not set in ApiSession. Make sure authentication middleware is applied.'
            );
        }

        return $this->sysPlayerId;
    }

    /**
     * プレイヤーIDが設定されているか確認（インスタンスメソッド）
     *
     * @return bool
     */
    public function hasPlayerId(): bool
    {
        return $this->sysPlayerId !== null;
    }

    /**
     * リクエスト開始時刻を設定（インスタンスメソッド）
     *
     * @param CarbonImmutable $now リクエスト開始時刻
     * @return void
     */
    public function setNow(CarbonImmutable $now): void
    {
        $this->now = $now;
    }

    /**
     * リクエスト開始時刻を取得（インスタンスメソッド）
     *
     * @return CarbonImmutable リクエスト開始時刻
     * @throws \RuntimeException リクエスト開始時刻が設定されていない場合
     */
    public function getNowValue(): CarbonImmutable
    {
        if ($this->now === null) {
            throw new \RuntimeException(
                'Request time is not set in ApiSession. Make sure authentication middleware is applied.'
            );
        }

        return $this->now;
    }

    /**
     * リクエスト開始時刻が設定されているか確認（インスタンスメソッド）
     *
     * @return bool
     */
    public function hasNowValue(): bool
    {
        return $this->now !== null;
    }

    /**
     * セッション情報をクリア（主にテスト用）
     *
     * @return void
     */
    public function clear(): void
    {
        $this->sysPlayerId = null;
        $this->now = null;
    }

    /**
     * 静的ヘルパー: プレイヤーIDとリクエスト開始時刻を設定
     * Middlewareから簡単に呼び出せるヘルパーメソッド
     * リクエスト開始時刻はClock::now()で自動的に設定される
     *
     * @param int $sysPlayerId プレイヤーID
     * @return void
     */
    public static function setSysPlayerId(int $sysPlayerId): void
    {
        $now = Clock::now();
        
        if (!app()->bound(self::class)) {
            app()->instance(self::class, new self($sysPlayerId, $now));
        } else {
            $instance = app(self::class);
            $instance->setPlayerId($sysPlayerId);
            $instance->setNow($now);
        }
    }

    /**
     * 静的ヘルパー: プレイヤーIDを取得
     * どこからでも簡単に呼び出せるヘルパーメソッド
     *
     * @return int プレイヤーID
     * @throws \RuntimeException プレイヤーIDが設定されていない場合
     */
    public static function getSysPlayerId(): int
    {
        return app(self::class)->getPlayerId();
    }

    /**
     * 静的ヘルパー: プレイヤーIDが設定されているか確認
     *
     * @return bool
     */
    public static function hasSysPlayerId(): bool
    {
        if (!app()->bound(self::class)) {
            return false;
        }

        return app(self::class)->hasPlayerId();
    }

    /**
     * 静的ヘルパー: リクエスト開始時刻を取得
     * どこからでも簡単に呼び出せるヘルパーメソッド
     *
     * @return CarbonImmutable リクエスト開始時刻
     * @throws \RuntimeException リクエスト開始時刻が設定されていない場合
     */
    public static function getNow(): CarbonImmutable
    {
        return app(self::class)->getNowValue();
    }

    /**
     * 静的ヘルパー: リクエスト開始時刻が設定されているか確認
     *
     * @return bool
     */
    public static function hasNow(): bool
    {
        if (!app()->bound(self::class)) {
            return false;
        }

        return app(self::class)->hasNowValue();
    }

    /**
     * 静的ヘルパー: テスト用にApiSessionをクリア
     *
     * @return void
     */
    public static function clearForTest(): void
    {
        if (app()->bound(self::class)) {
            app(self::class)->clear();
        }
    }
}
