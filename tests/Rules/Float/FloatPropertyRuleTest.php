<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Tests\Rules\Float;

use IanFoxDev\MoneyLint\MoneyDetector;
use IanFoxDev\MoneyLint\Rules\Float\FloatPropertyRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<FloatPropertyRule>
 */
final class FloatPropertyRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../config/patterns.neon'];
    }

    protected function getRule(): Rule
    {
        return new FloatPropertyRule(self::getContainer()->getByType(MoneyDetector::class));
    }

    public function testFloatMoneyProperties(): void
    {
        $tip = 'Keep amounts as integers in minor units, decimal strings, or money objects. See https://github.com/IanFoxDev/money-lint/blob/master/docs/rules/float.md';
        $message = static fn(string $property): string => sprintf(
            'Property FloatProperties\Order::$%s holds money and is typed float: floats cannot store most decimal amounts exactly.',
            $property,
        );

        $this->analyse([__DIR__ . '/data/properties.php'], [
            [$message('amount'), 11, $tip],
            [$message('refundAmount'), 13, $tip],
            [$message('balance'), 15, $tip],
            [$message('total'), 18, $tip],
            [$message('discountAmount'), 21, $tip],
            [$message('shippingAmount'), 24, $tip],
            [$message('tip'), 25, $tip],
        ]);
    }
}
