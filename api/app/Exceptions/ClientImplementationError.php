<?php

namespace App\Exceptions;

/**
 * ClientImplementationError
 *
 * クライアントの実装の誤り（付け忘れ・食い違い）を表す GameException に付ける目印。
 *
 * 目印の付いた例外は、_BaseController::handleException と bootstrap/app.php の
 * GameException ハンドラで次のように扱う。
 * - Slack へ通知しない: 利用者の操作ではなく実装の誤りで、通知しても運営は何もできず、
 *   誤ったクライアントが出回ると通知が溢れるため（ResolvePlatform と同じ考え方）
 * - 本番でもメッセージをマスクしない: 実装者が原因を読めるようにするため
 *   （メッセージに内部情報を含めないこと）
 * - ログ（Log::error）には従来どおり残す
 *
 * 例: 購入の body の platform と X-Platform の不一致（PlatformMismatchException）
 */
interface ClientImplementationError {}
