<?php

namespace Tests\Unit\PHPStan;

use App\PHPStan\Rules\NoGetAttributeOutsideModelsRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<NoGetAttributeOutsideModelsRule>
 */
class NoGetAttributeOutsideModelsRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new NoGetAttributeOutsideModelsRule;
    }

    public function test_reports_get_and_set_attribute_outside_models(): void
    {
        $message = 'モデルの外から %s() を呼ばないでください。モデルの getter / setter を使ってください（足りなければモデルに追加する）。';

        $this->analyse([__DIR__.'/../../Fixtures/PHPStan/Outside/ModelAccess.php'], [
            [sprintf($message, 'getAttribute'), 12],
            [sprintf($message, 'setAttribute'), 13],
        ]);
    }

    public function test_allows_get_attribute_inside_models_directory(): void
    {
        $this->analyse([__DIR__.'/../../Fixtures/PHPStan/Models/InsideModel.php'], []);
    }
}
