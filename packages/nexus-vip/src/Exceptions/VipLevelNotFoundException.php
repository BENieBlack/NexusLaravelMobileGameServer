<?php

namespace NexusVip\Exceptions;

use Exception;

/**
 * VIPレベルが見つからない場合の例外
 */
class VipLevelNotFoundException extends Exception
{
    public function __construct(string $message = 'VIP level not found', int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
