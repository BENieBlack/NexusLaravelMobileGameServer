<?php

namespace Tests\Unit\PHPStan;

use App\PHPStan\Rules\NoDirectEloquentSaveInServiceRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoDirectEloquentSaveInServiceRule>
 */
class NoDirectEloquentSaveInServiceRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoDirectEloquentSaveInServiceRule;
    }

    public function test_reports_direct_save_update_delete_in_services(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/PHPStan/Domain/Sample/Services/SaveInService.php'], [
            [$this->message('save'), 11],
            [$this->message('update'), 12],
            [$this->message('delete'), 13],
            [$this->message('forceDelete'), 14],
        ]);
    }

    private function message(string $method): string
    {
        return sprintf(
            'Service層で直接Eloquentの %s() を呼び出さないでください。'.PHP_EOL.
            'Repository経由で操作してください：'.PHP_EOL.
            '  - Trx Repository: $this->repository->setModel($model)'.PHP_EOL.
            '  - Sys Repository: $this->repository->updatePlayer($player)',
            $method
        );
    }
}
