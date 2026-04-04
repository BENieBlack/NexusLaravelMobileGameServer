<?php

namespace App\Domain\Delivery\Enums;

/**
 * DeliveryMethod
 *
 * 配送方法を表すEnum
 */
enum DeliveryMethod: string
{
    // 未指定。各リソースタイプごとのHandlerの処理に任せる
    case NONE = 'none';

    // 上限チェックして超過したらメールボックスへ送信
    case SEND_TO_MAILBOX = 'send_to_mailbox';

    // 上限チェックをして超過したらエラーを出す
    case THROW_ERROR_WHEN_RESOURCE_LIMIT_REACHED = 'throw_error_when_resource_limit_reached';
}
