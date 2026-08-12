<?php

use App\Http\Middleware\CheckMaintenance;
use App\Http\Middleware\VerifyAdminToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use NexusSecurity\Middleware\IdempotencyMiddleware;
use NexusSecurity\Middleware\ThrottleAuth;
use NexusSecurity\Middleware\ThrottleSignUp;
use NexusSecurity\Middleware\VerifyAccessToken;
use NexusSecurity\Middleware\VerifyClientSignature;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ミドルウェアエイリアスを登録
        $middleware->alias([
            'auth.token' => VerifyAccessToken::class,
            'auth.admin' => VerifyAdminToken::class,
            'idempotency' => IdempotencyMiddleware::class,
            'client.signature' => VerifyClientSignature::class,
            'throttle.signup' => ThrottleSignUp::class,
            'throttle.auth' => ThrottleAuth::class,
            'maintenance' => CheckMaintenance::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Load environment variables from project root
// Use parent directory of api folder (one level up from basePath)
$app->useEnvironmentPath(dirname($app->basePath()));

return $app;
