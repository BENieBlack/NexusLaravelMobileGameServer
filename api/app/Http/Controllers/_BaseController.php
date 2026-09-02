<?php

namespace App\Http\Controllers;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Exceptions\InfraErrorCode;
use App\Http\Responses\_BaseResponseInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;
use Nexus\Core\ValueObjects\ErrorResponse;
use NexusSecurity\Services\SlackErrorNotifier;
use Throwable;

abstract class _BaseController
{
    /**
     * UseCaseを実行し、レスポンスを返す
     *
     * UseCaseは必ず _BaseResponseInterface の実装を返すこと。
     * 戻り値の型を固定することで、レスポンスの形が呼び出し方によって
     * 変わったり、意図しないキーが混入したりするのを防ぐ。
     *
     * @param  callable(): _BaseResponseInterface  $useCase  UseCaseの実行関数（例: fn() => $useCase->exec($request)）
     */
    protected function execute(callable $useCase): JsonResponse
    {
        try {
            /** @var mixed $response */
            $response = $useCase();

            if (! $response instanceof _BaseResponseInterface) {
                throw new LogicException(sprintf(
                    'UseCaseは %s を実装したレスポンスを返す必要があります。返り値: %s',
                    _BaseResponseInterface::class,
                    get_debug_type($response)
                ));
            }

            return $response->toJsonResponse();

        } catch (Throwable $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 認証済みプレイヤーIDを取り出す（無ければ例外）
     *
     * 実際にはauth.tokenミドルウェアが先に401を返すため、ここは通らない。
     * 認証グループの外にルートを置いてしまったときの保険と、
     * ?int を int に絞るための処理を兼ねている。
     *
     * @throws GameException 認証情報が無い場合
     */
    protected function requireAuthenticatedPlayerId(?int $sysPlayerId): int
    {
        if (! $sysPlayerId) {
            throw new GameException(
                GameErrorCode::AUTHENTICATION_FAILED,
                'Player ID not found in request'
            );
        }

        return $sysPlayerId;
    }

    /**
     * 例外をハンドリングし、エラーレスポンスを返す
     *
     * GameException（ビジネスロジックエラー）: HTTP 299 + {error_code: int, message: string}
     * その他の例外（システムエラー）: HTTP 500等 + {error_code: int, message: string}
     *
     * HTTP 299を使用する理由:
     * - 2xx範囲だがHTTP 200（成功）と明確に区別できる
     * - クライアント側でステータスコードで分岐可能
     * - レスポンスボディにerror_codeを含むことでエラー種別を詳細に特定
     *
     * 本番環境では内部エラー詳細を隠し、ログに記録する
     */
    protected function handleException(Throwable $e): JsonResponse
    {
        /** @var Request $request */
        $request = request();

        // GameExceptionの場合はHTTP 299のビジネスロジックエラー
        if ($e instanceof GameException) {
            $errorCode = $e->getErrorCode();

            // ログに記録
            \Log::error('Exception in API request', [
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'code'      => $errorCode,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'trace'     => $e->getTraceAsString(),
            ]);

            // Slackへ通知
            $this->notifySlack($e, $request, $errorCode);

            $errorResponse = ErrorResponse::businessError(
                errorCode: $errorCode,
                message: $e->getMessage()
            );

            // 本番環境ではメッセージをマスク
            return $errorResponse->maskForProduction()->toJsonResponse();
        }

        // その他の例外はシステムエラーとして扱う
        $httpStatus = $this->determineStatusCode($e);
        $errorCode  = $e->getCode() ?: InfraErrorCode::UNKNOWN_ERROR;

        // ログに記録
        \Log::error('Exception in API request', [
            'exception' => get_class($e),
            'message'   => $e->getMessage(),
            'code'      => $errorCode,
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]);

        // Slackへ通知
        $this->notifySlack($e, $request, $errorCode);

        $errorResponse = ErrorResponse::withStatus(
            errorCode: $errorCode,
            message: $e->getMessage(),
            httpStatus: $httpStatus
        );

        // 本番環境ではメッセージをマスク
        return $errorResponse->maskForProduction()->toJsonResponse();
    }

    /**
     * 例外からHTTPステータスコードを決定
     */
    protected function determineStatusCode(Throwable $e): int
    {
        $code = $e->getCode();

        // HTTPステータスコードとして有効な範囲（100-599）かチェック
        if (is_int($code) && $code >= 100 && $code < 600) {
            return $code;
        }

        // デフォルトは500（Internal Server Error）
        return 500;
    }

    /**
     * Slackへエラー通知を送信する
     *
     * 通知失敗（Slack側の障害・設定ミス等）がAPIレスポンスに影響しないよう
     * 例外を握り潰す。
     */
    private function notifySlack(Throwable $e, Request $request, int $errorCode): void
    {
        try {
            /** @var SlackErrorNotifier $notifier */
            $notifier = app(SlackErrorNotifier::class);
            $notifier->notify($e, $request, $errorCode);
        } catch (Throwable) {
            // Slack通知の失敗はAPIレスポンスに影響させない
        }
    }
}
