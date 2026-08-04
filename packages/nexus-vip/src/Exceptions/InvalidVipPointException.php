<?php

namespace NexusVip\Exceptions;

use Exception;

/**
 * 不正なVIPポイント操作の例外
 */
class InvalidVipPointException extends Exception
{
    public function __construct(string $message = 'Invalid VIP point operation', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
