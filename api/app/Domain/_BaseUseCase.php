<?php

namespace App\Domain;

/**
 * _BaseUseCase
 * 
 * すべてのUseCaseの基底クラス
 * 共通のロジックやヘルパーメソッドを提供
 * 
 * 各UseCaseは独自のhandle()シグネチャを持つため、
 * ここではhandleメソッドを抽象メソッドとして定義しない
 */
abstract class _BaseUseCase implements _BaseUseCaseInterface
{
    // 共通のヘルパーメソッドがあれば、ここに追加する
    // 例：
    // protected function logUseCaseExecution(string $useCaseName): void
    // {
    //     Log::info("Executing UseCase: {$useCaseName}");
    // }
}
