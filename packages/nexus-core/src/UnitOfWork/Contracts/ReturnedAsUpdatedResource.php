<?php

namespace Nexus\Core\UnitOfWork\Contracts;

/**
 * ReturnedAsUpdatedResource
 *
 * クライアントに「更新されたもの」として返すモデルの目印。
 * このインターフェースを実装したモデルだけが、UpdatedResources に入る（許可リスト方式）。
 *
 * 新しいテーブルを足しても、これを付けない限りクライアントには出ない。
 * 付けるのは、画面に出る状態（所持品、通貨、スタミナなど）だけにする。
 * 内訳・履歴・内部用のテーブルには付けない。
 */
interface ReturnedAsUpdatedResource {}
