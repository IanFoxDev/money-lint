<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Tests\Rules\Float;

use IanFoxDev\MoneyLint\MoneyExpressions;
use IanFoxDev\MoneyLint\Rules\Float\FloatConversionRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<FloatConversionRule>
 */
final class FloatConversionRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../config/patterns.neon'];
    }

    protected function getRule(): Rule
    {
        return new FloatConversionRule(self::getContainer()->getByType(MoneyExpressions::class));
    }

    public function testMoneyTurnedIntoFloat(): void
    {
        $tip = 'Keep amounts as integers in minor units, decimal strings, or money objects. See https://github.com/IanFoxDev/money-lint/blob/master/docs/rules/float.md';

        $this->analyse([__DIR__ . '/data/conversions.php'], [
            ['Money $payment->amount is cast to float.', 23, $tip],
            ['Money $payment->total is cast to float.', 24, $tip],
            ['Money $amount is converted to float by floatval().', 25, $tip],
            ['Money $payment->amount is rounded to decimal places, so it is held as a float.', 26, $tip],
            ['Money $payment->amount is rounded to decimal places, so it is held as a float.', 27, $tip],
            ['Float literal is written into money $payment->amount.', 28, $tip],
            ['Float literal is written into money $payment->amount.', 29, $tip],
            ['Money $maybe?->amount is cast to float.', 30, $tip],
            ['Float literal is written into money \\FloatConversions\\Payment::$balance.', 31, $tip],
            ['Money \\FloatConversions\\Payment::$balance is cast to float.', 32, $tip],
        ]);
    }
}
