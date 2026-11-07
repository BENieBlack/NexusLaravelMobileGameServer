<?php

namespace App\Domain;

use Attribute;

/**
 * DoesNotReturnUpdatedResources
 *
 * UseCase が trx（ユニット・アイテム・ダイヤなど）を更新するのに、updated_resources を返さないことの宣言
 *
 * 後続の API で現在値を全て取り直す（auth/sign_up → auth/login など）、または現在値を全て返す API で、
 * 更新分だけを返す必要がないときに付ける。理由を引数に書く。
 * 付けずに更新すると、開発・テスト環境の executeWithTransaction() が例外にする（返し忘れの検出）。
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class DoesNotReturnUpdatedResources
{
    public function __construct(public readonly string $reason) {}
}
