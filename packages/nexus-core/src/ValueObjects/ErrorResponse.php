<?php

namespace Nexus\Core\ValueObjects;

use Illuminate\Http\JsonResponse;

/**
 * ErrorResponse
 * 
 * HTTP 299エラーレスポンス（ビジネスロジックエラー）およびその他のエラーレスポンスを表すValueObject
 * 
 * このクラスはnexus-core-utilitiesパッケージに配置され、
 * アプリケーション層とパッケージ層の両方から使用可能
 * 
 * HTTP 299を使用する理由:
 * - 2xx範囲なのでネットワーク/プロキシレベルではエラーとして扱われない
 * - HTTP 200（完全な成功）と明確に区別できる
 * - クライアント側で if (status === 299) { handleBusinessError() } のように分岐可能
 * - レスポンスボディのerror_codeでエラー種別を詳細に特定
 * 
 * エラーコード体系:
 * - 3桁（1-999）: インフラ/汎用エラー（DB接続失敗101, Redis接続失敗201, 不明エラー999等）
 * - 4桁（1000-9999）: パッケージ層エラー（nexus-wallet: 1000-1099, nexus-stamina: 1100-1199等）
 * - 5桁（10000-99999）: アプリケーション層エラー（認証10000台, プレイヤー11000台等）
 * 
 * 使用例:
 * ```php
 * // アプリケーション層: ビジネスロジックエラー（HTTP 299）
 * use App\Exceptions\GameErrorCode;
 * return ErrorResponse::businessError(
 *     errorCode: GameErrorCode::PLAYER_NOT_FOUND,
 *     message: 'プレイヤーが見つかりません'
 * )->toJsonResponse();
 * 
 * // パッケージ層: パッケージエラー（HTTP 299）
 * use NexusWallet\Exceptions\WalletErrorCode;
 * return ErrorResponse::businessError(
 *     errorCode: WalletErrorCode::INSUFFICIENT_BALANCE,
 *     message: '残高が不足しています'
 * )->toJsonResponse();
 * 
 * // インフラ層: システムエラー（HTTP 500）
 * use App\Exceptions\InfraErrorCode;
 * return ErrorResponse::systemError(
 *     errorCode: InfraErrorCode::UNKNOWN_ERROR,
 *     message: 'An error occurred'
 * )->toJsonResponse();
 * ```
 */
final class ErrorResponse
{
    /**
     * @param int $errorCode アプリケーション固有のエラーコード（3桁～5桁）
     * @param string $message エラーメッセージ
     * @param int $httpStatus HTTPステータスコード（デフォルト: 299）
     */
    private function __construct(
        private readonly int $errorCode,
        private readonly string $message,
        private readonly int $httpStatus = 299
    ) {}

    /**
     * ビジネスロジックエラーを作成（HTTP 299）
     * 
     * アプリケーション層（5桁）またはパッケージ層（4桁）のビジネスエラーに使用
     * 
     * @param int $errorCode 4桁または5桁のエラーコード
     * @param string $message エラーメッセージ
     * @return self
     */
    public static function businessError(int $errorCode, string $message): self
    {
        return new self($errorCode, $message, 299);
    }

    /**
     * インフラエラーを作成（HTTP 500）
     * 
     * DB接続失敗、Redis接続失敗等のインフラレベルエラー（3桁）に使用
     * 
     * @param int $errorCode 3桁のインフラエラーコード
     * @param string $message エラーメッセージ
     * @return self
     */
    public static function systemError(int $errorCode, string $message): self
    {
        return new self($errorCode, $message, 500);
    }

    /**
     * カスタムHTTPステータスコードでエラーを作成
     * 
     * @param int $errorCode エラーコード
     * @param string $message エラーメッセージ
     * @param int $httpStatus HTTPステータスコード（400, 401, 403, 404等）
     * @return self
     */
    public static function withStatus(int $errorCode, string $message, int $httpStatus): self
    {
        return new self($errorCode, $message, $httpStatus);
    }

    /**
     * 本番環境用にメッセージをマスクしたインスタンスを作成
     * 
     * @return self
     */
    public function maskForProduction(): self
    {
        if (config('app.env') === 'production') {
            return new self(
                $this->errorCode,
                'An error occurred. Please contact support.',
                $this->httpStatus
            );
        }

        return $this;
    }

    /**
     * エラーコードを取得
     * 
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * エラーメッセージを取得
     * 
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * HTTPステータスコードを取得
     * 
     * @return int
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * 配列に変換
     * 
     * @return array{error_code: int, message: string}
     */
    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode,
            'message' => $this->message,
        ];
    }

    /**
     * JSON シリアライズ
     * 
     * @return array{error_code: int, message: string}
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * JsonResponseに変換
     * 
     * HTTPステータスコードは初期化時に指定された値を使用
     * 
     * @param int|null $status HTTPステータスコード（指定された場合は上書き）
     * @return JsonResponse
     */
    public function toJsonResponse(?int $status = null): JsonResponse
    {
        return response()->json($this->toArray(), $status ?? $this->httpStatus);
    }
}
