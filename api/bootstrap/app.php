<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
            'auth.token' => \LaravelSecurityMiddleware\Middleware\VerifyAccessToken::class,
            'idempotency' => \LaravelSecurityMiddleware\Middleware\IdempotencyMiddleware::class,
            'client.signature' => \LaravelSecurityMiddleware\Middleware\VerifyClientSignature::class,
            'throttle.signup' => \LaravelSecurityMiddleware\Middleware\ThrottleSignUp::class,
            'maintenance' => \App\Http\Middleware\CheckMaintenance::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

// Load environment variables from project root
// Use parent directory of api folder (one level up from basePath)
$app->useEnvironmentPath(dirname($app->basePath()));

return $app;
