<?php

namespace Tests\Unit\PHPStan;

use App\PHPStan\Rules\NoModelColumnPropertyAccessRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoModelColumnPropertyAccessRule>
 */
class NoModelColumnPropertyAccessRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoModelColumnPropertyAccessRule;
    }

    public function test_reports_column_property_access_outside_models(): void
    {
        $message = 'モデルの外から列 $free_amount をプロパティで読み書きしないでください。モデルの getter / setter を使ってください（足りなければモデルに追加する）。[Tests\Fixtures\PHPStan\Outside\SampleModel]';

        $this->analyse([__DIR__.'/../../Fixtures/PHPStan/Outside/ModelAccess.php'], [
            [$message, 14],
            [$message, 15],
        ]);
    }

    public function test_allows_property_access_inside_models_directory(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/PHPStan/Models/InsideModel.php'], []);
    }
}
