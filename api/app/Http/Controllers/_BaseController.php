<?php

namespace App\Http\Controllers;

use App\Exceptions\GameErrorCode;
use App\Exceptions\GameException;
use App\Exceptions\InfraErrorCode;
use App\Responses\_BaseResponseInterface;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Nexus\Core\ValueObjects\ErrorResponse;
use Throwable;

abstract class _BaseController
{
    /**
     * UseCaseを実行し、レスポンスを返す
     *
     * エラーハンドリングを含む共通処理
     *
     * @param  callable  $useCase  UseCaseの実行関数（例: fn() => $useCase->exec($request)）
     */
    protected function execute(callable $useCase): JsonResponse
    {
        try {
            $response = $useCase();

            // レスポンスがResponsableを実装している場合（Laravelの標準インターフェイス）
            if ($response instanceof Responsable) {
                return $response->toResponse(request());
            }

            // レスポンスが_BaseResponseInterfaceを実装している場合
            if ($response instanceof _BaseResponseInterface) {
                return $response->toJsonResponse();
            }

            // JsonResponseが直接返された場合
            if ($response instanceof JsonResponse) {
                return $response;
            }

            // 配列が返された場合
            if (is_array($response)) {
                return response()->json($response);
            }

            // それ以外の場合はそのまま返す
            return response()->json(['data' => $response]);

        } catch (Throwable $e) {
            return $this->handleException($e);
        }
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
        // 例外の詳細をログに記録
        \Log::error('Exception in API request', [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        // GameExceptionの場合はHTTP 299のビジネスロジックエラー
        if ($e instanceof GameException) {
            $errorResponse = ErrorResponse::businessError(
                errorCode: $e->getErrorCode(),
                message: $e->getMessage()
            );

            // 本番環境ではメッセージをマスク
            return $errorResponse->maskForProduction()->toJsonResponse();
        }

        // その他の例外はシステムエラーとして扱う
        $httpStatus = $this->determineStatusCode($e);
        $errorCode = $e->getCode() ?: InfraErrorCode::UNKNOWN_ERROR;

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
     * 成功レスポンスを返す
     *
     * @param  mixed  $data
     */
    protected function success($data = null, int $status = 200): JsonResponse
    {
        if ($data === null) {
            return response()->json([], $status);
        }

        if (is_array($data)) {
            return response()->json($data, $status);
        }

        return response()->json(['data' => $data], $status);
    }

    /**
     * エラーレスポンスを返す
     *
     * @param  mixed  $code
     */
    protected function error(string $message, int $status = 400, $code = null): JsonResponse
    {
        $response = ['error' => $message];

        if ($code !== null) {
            $response['code'] = $code;
        }

        return response()->json($response, $status);
    }
}
