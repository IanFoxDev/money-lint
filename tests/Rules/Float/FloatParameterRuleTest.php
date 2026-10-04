<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Tests\Rules\Float;

use IanFoxDev\MoneyLint\MoneyDetector;
use IanFoxDev\MoneyLint\Rules\Float\FloatParameterRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<FloatParameterRule>
 */
final class FloatParameterRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../config/patterns.neon'];
    }

    protected function getRule(): Rule
    {
        return new FloatParameterRule(self::getContainer()->getByType(MoneyDetector::class));
    }

    public function testFloatMoneyParameters(): void
    {
        $tip = 'Keep amounts as integers in minor units, decimal strings, or money objects. See https://github.com/IanFoxDev/money-lint/blob/master/docs/rules/float.md';
        $message = static fn(string $parameter): string => sprintf(
            'Parameter $%s holds money and is typed float: floats cannot store most decimal amounts exactly.',
            $parameter,
        );

        $this->analyse([__DIR__ . '/data/parameters.php'], [
            [$message('amount'), 9, $tip],
            [$message('balance'), 9, $tip],
            [$message('total'), 9, $tip],
            [$message('amount'), 11, $tip],
            [$message('amount'), 15, $tip],
        ]);
    }
}
