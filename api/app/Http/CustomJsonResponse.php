<?php

namespace App\Http;

use Illuminate\Http\JsonResponse as BaseJsonResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * CustomJsonResponse
 * 
 * 600番台のカスタムHTTPステータスコードをサポートするJsonResponse
 * SymfonyのResponse制限（100-599）を回避する
 */
class CustomJsonResponse extends BaseJsonResponse
{
    /**
     * 600番台のカスタムステータスコード範囲
     */
    private const CUSTOM_STATUS_MIN = 600;
    private const CUSTOM_STATUS_MAX = 699;

    /**
     * カスタムステータスコードのテキストマッピング
     */
    private const CUSTOM_STATUS_TEXTS = [
        600 => 'Game Error',
        601 => 'Maintenance',
        602 => 'Force Update Required',
        603 => 'Account Suspended',
        604 => 'Server Overload',
        605 => 'Fraud Detected',
    ];

    /**
     * コンストラクタ
     *
     * @param mixed $data
     * @param int $status
     * @param array $headers
     * @param int $options
     * @param bool $json
     */
    public function __construct($data = null, $status = 200, $headers = [], $options = 0, $json = false)
    {
        // 600番台のステータスコードの場合、一時的に200で初期化
        $isCustomStatus = $this->isCustomStatusCode($status);
        $actualStatus = $isCustomStatus ? 200 : $status;
        
        parent::__construct($data, $actualStatus, $headers, $options, $json);
        
        // カスタムステータスコードを直接設定
        if ($isCustomStatus) {
            $this->setStatusCodeDirectly($status);
        }
    }

    /**
     * カスタムステータスコードかどうかを判定
     *
     * @param int $statusCode
     * @return bool
     */
    private function isCustomStatusCode(int $statusCode): bool
    {
        return $statusCode >= self::CUSTOM_STATUS_MIN && $statusCode <= self::CUSTOM_STATUS_MAX;
    }

    /**
     * ステータスコードを直接設定（Symfony検証回避）
     *
     * @param int $code
     * @return void
     */
    private function setStatusCodeDirectly(int $code): void
    {
        // Reflectionを使ってprotectedプロパティに直接アクセス
        $reflection = new \ReflectionClass(SymfonyResponse::class);
        
        // statusCodeプロパティを取得
        $statusCodeProperty = $reflection->getProperty('statusCode');
        $statusCodeProperty->setAccessible(true);
        $statusCodeProperty->setValue($this, $code);
        
        // statusTextプロパティを取得
        $statusTextProperty = $reflection->getProperty('statusText');
        $statusTextProperty->setAccessible(true);
        $statusTextProperty->setValue($this, self::CUSTOM_STATUS_TEXTS[$code] ?? 'Custom Status');
    }

    /**
     * ステータスコードを設定
     * 
     * 600番台のカスタムコードをサポート
     *
     * @param int $code
     * @param string|null $text
     * @return static
     */
    public function setStatusCode(int $code, $text = null): static
    {
        if ($this->isCustomStatusCode($code)) {
            $this->setStatusCodeDirectly($code);
            return $this;
        }
        
        return parent::setStatusCode($code, $text);
    }
}
