<?php

namespace NexusUtilities\Traits;

/**
 * JsonSerializableTrait
 * 
 * DTOクラスに共通のJSON変換機能を提供するTrait
 * toArray()メソッドを実装しているクラスで使用可能
 */
trait JsonSerializableTrait
{
    /**
     * JSON文字列に変換
     * 
     * @return string JSON文字列
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
