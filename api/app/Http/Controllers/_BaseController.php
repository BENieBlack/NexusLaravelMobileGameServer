<?php

namespace App\Http\Controllers;

use App\Exceptions\GameException;
use App\Exceptions\HttpStatusCode;
use App\Http\CustomJsonResponse;
use App\Responses\_BaseResponseInterface;
use Illuminate\Http\JsonResponse;
use Throwable;

abstract class _BaseController
{
    /**
     * UseCaseを実行し、レスポンスを返す
     * 
     * エラーハンドリングを含む共通処理
     *
     * @param callable $useCase UseCaseの実行関数（例: fn() => $useCase->handle($request)）
     * @return JsonResponse
     */
    protected function execute(callable $useCase): JsonResponse
    {
        try {
            $response = $useCase();
            
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
     * GameException: HTTP 600 + {error_code: int, message: string}
     * その他: HTTP status + {error_code: int, message: string}
     *
     * @param Throwable $e
     * @return JsonResponse
     */
    protected function handleException(Throwable $e): JsonResponse
    {
        // GameExceptionの場合は600ステータスでerror_codeとmessageを返す
        if ($e instanceof GameException) {
            return new CustomJsonResponse($e->toArray(), HttpStatusCode::GAME_ERROR);
        }

        // その他の例外も同じ形式で返す（統一性のため）
        $code = $this->determineStatusCode($e);
        
        return response()->json([
            'error_code' => $e->getCode() ?: 19999, // デフォルトはINTERNAL_ERROR
            'message' => $e->getMessage(),
        ], $code);
    }

    /**
     * 例外からHTTPステータスコードを決定
     *
     * @param Throwable $e
     * @return int
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
     * @param mixed $data
     * @param int $status
     * @return JsonResponse
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
     * @param string $message
     * @param int $status
     * @param mixed $code
     * @return JsonResponse
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
