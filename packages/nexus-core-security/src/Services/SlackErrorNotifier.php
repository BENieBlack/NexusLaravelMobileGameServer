<?php

namespace NexusSecurity\Services;

use Illuminate\Http\Request;
use Throwable;

/**
 * SlackErrorNotifier
 *
 * APIエラー発生時にSlackへ通知するサービス。
 * nexus-core-securityパッケージが提供する。
 *
 * 通知内容:
 * - URL
 * - errorCode
 * - message
 * - スタックトレース（上位10行）
 * - POSTのHTTPヘッダ
 * - POSTのBody
 *
 * 設定:
 * - SLACK_ERROR_POST_CHANNEL: 通知先チャンネルID（api/.env）
 * - SLACK_BOT_TOKEN: SlackのBotトークン（グローバル設定）
 * - security.slack_ignore_error_codes: 通知しないエラーコードの配列（config/security.php）
 */
class SlackErrorNotifier
{
    /**
     * エラー通知を送信する
     *
     * @param  Throwable  $e  発生した例外
     * @param  Request  $request  HTTPリクエスト
     * @param  int  $errorCode  エラーコード
     */
    public function notify(Throwable $e, Request $request, int $errorCode): void
    {
        $channel = config('security.slack_error_channel');
        $token = config('security.slack_bot_token');

        if (empty($channel) || empty($token)) {
            return;
        }

        // ignore設定に含まれるエラーコードは通知しない
        if ($this->isIgnored($errorCode)) {
            return;
        }

        $payload = $this->buildPayload($e, $request, $errorCode, $channel);

        $this->post($token, $payload);
    }

    /**
     * 指定エラーコードが通知対象外か判定する
     *
     * config('security.slack_ignore_error_codes') に含まれる場合は通知しない。
     * AppServiceProviderなどで上書き可能:
     *
     *   config(['security.slack_ignore_error_codes' => [10001, 10002, 10003]]);
     *
     * @param  int  $errorCode
     * @return bool
     */
    private function isIgnored(int $errorCode): bool
    {
        $ignoreCodes = config('security.slack_ignore_error_codes', []);

        return in_array($errorCode, $ignoreCodes, true);
    }

    /**
     * Slack API に投稿する
     *
     * @param  string  $token  Slack Bot Token
     * @param  array<string, mixed>  $payload  リクエストボディ
     */
    private function post(string $token, array $payload): void
    {
        $ch = curl_init('https://slack.com/api/chat.postMessage');

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                "Authorization: Bearer {$token}",
            ],
            CURLOPT_TIMEOUT => 5,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Slack メッセージのペイロードを構築する
     *
     * @param  Throwable  $e
     * @param  Request  $request
     * @param  int  $errorCode
     * @param  string  $channel
     * @return array<string, mixed>
     */
    private function buildPayload(Throwable $e, Request $request, int $errorCode, string $channel): array
    {
        $url = $request->fullUrl();
        $message = $e->getMessage();
        $traceLines = $this->formatStackTrace($e, 10);
        $headers = $this->formatHeaders($request);
        $body = $this->formatBody($request);
        $exceptionClass = get_class($e);
        $env = config('app.env', 'unknown');

        $text = implode("\n", [
            ":rotating_light: *APIエラー発生* [{$env}]",
            "*URL:* `{$url}`",
            "*ErrorCode:* `{$errorCode}`",
            "*Exception:* `{$exceptionClass}`",
            "*Message:* {$message}",
        ]);

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => $text,
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*スタックトレース（上位10行）:*\n```{$traceLines}```",
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*POSTヘッダ:*\n```{$headers}```",
                ],
            ],
            [
                'type' => 'section',
                'text' => [
                    'type' => 'mrkdwn',
                    'text' => "*POSTボディ:*\n```{$body}```",
                ],
            ],
        ];

        return [
            'channel' => $channel,
            'text' => "[APIエラー] {$url} - {$errorCode}: {$message}",
            'blocks' => $blocks,
        ];
    }

    /**
     * スタックトレースを上位N行に整形する
     *
     * @param  Throwable  $e
     * @param  int  $limit
     * @return string
     */
    private function formatStackTrace(Throwable $e, int $limit): string
    {
        $lines = explode("\n", $e->getTraceAsString());
        $lines = array_slice($lines, 0, $limit);

        return implode("\n", $lines);
    }

    /**
     * リクエストヘッダを整形する（機密ヘッダはマスク）
     *
     * @param  Request  $request
     * @return string
     */
    private function formatHeaders(Request $request): string
    {
        // POSTリクエスト以外はヘッダなし表記
        if ($request->method() !== 'POST') {
            return '(POST以外のリクエスト)';
        }

        $sensitiveKeys = ['authorization', 'x-api-key', 'cookie', 'x-client-signature'];
        $lines = [];

        foreach ($request->headers->all() as $key => $values) {
            $lowerKey = strtolower($key);
            $value = in_array($lowerKey, $sensitiveKeys, true)
                ? '***MASKED***'
                : implode(', ', $values);

            $lines[] = "{$key}: {$value}";
        }

        return implode("\n", $lines);
    }

    /**
     * リクエストボディを整形する（機密フィールドはマスク）
     *
     * @param  Request  $request
     * @return string
     */
    private function formatBody(Request $request): string
    {
        // POSTリクエスト以外はボディなし表記
        if ($request->method() !== 'POST') {
            return '(POST以外のリクエスト)';
        }

        $sensitiveFields = ['password', 'token', 'secret', 'refresh_token', 'access_token'];
        $body = $request->all();

        array_walk_recursive($body, function (mixed &$value, string $key) use ($sensitiveFields): void {
            if (in_array(strtolower($key), $sensitiveFields, true)) {
                $value = '***MASKED***';
            }
        });

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Slackのブロック文字数制限（3000文字）を考慮して切り詰める
        if (strlen($json) > 2000) {
            $json = mb_substr($json, 0, 2000)."\n... (truncated)";
        }

        return $json !== false ? $json : '(bodyの取得に失敗)';
    }
}
