<?php

use App\Exceptions\GameException;
use App\Http\Middleware\CheckMaintenance;
use App\Http\Middleware\CheckMasterHash;
use App\Http\Middleware\ResolveLanguage;
use App\Http\Middleware\VerifyAdminToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Nexus\Core\ValueObjects\ErrorResponse;
use NexusSecurity\Middleware\IdempotencyMiddleware;
use NexusSecurity\Middleware\ThrottleAuth;
use NexusSecurity\Middleware\ThrottlePublicRead;
use NexusSecurity\Middleware\ThrottleSignUp;
use NexusSecurity\Middleware\VerifyAccessToken;
use NexusSecurity\Middleware\VerifyClientSignature;
use NexusSecurity\Services\SlackErrorNotifier;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 全APIリクエストでAccept-Languageから言語を解決する
        $middleware->api(prepend: [
            ResolveLanguage::class,
            CheckMasterHash::class,
        ]);

        // ミドルウェアエイリアスを登録
        $middleware->alias([
            'auth.token' => VerifyAccessToken::class,
            'auth.admin' => VerifyAdminToken::class,
            'idempotency' => IdempotencyMiddleware::class,
            'client.signature' => VerifyClientSignature::class,
            'throttle.signup' => ThrottleSignUp::class,
            'throttle.auth' => ThrottleAuth::class,
            'throttle.public' => ThrottlePublicRead::class,
            'maintenance' => CheckMaintenance::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // GameExceptionはビジネスロジックエラーとしてHTTP 299 + error_codeで返す。
        //
        // 通常は _BaseController::execute() が捕捉するが、
        // Controllerが execute() の外で投げる場合（商品が見つからない、認証情報が無い等）は
        // ここを通る。拾わないとスタックトレース付きの500になり、
        // クライアントがエラー種別を判別できない。
        $exceptions->render(function (GameException $e) {
            $errorCode = $e->getErrorCode();

            Log::error('Exception in API request', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'code' => $errorCode,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Slackへ通知
            try {
                /** @var SlackErrorNotifier $notifier */
                $notifier = app(SlackErrorNotifier::class);
                $notifier->notify($e, request(), $errorCode);
            } catch (Throwable) {
                // Slack通知の失敗はAPIレスポンスに影響させない
            }

            return ErrorResponse::businessError(
                errorCode: $errorCode,
                message: $e->getMessage(),
            )->maskForProduction()->toJsonResponse();
        });
    })->create();

// Load environment variables from project root
// Use parent directory of api folder (one level up from basePath)
$app->useEnvironmentPath(dirname($app->basePath()));

return $app;
