<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Tests;

use IanFoxDev\MoneyLint\Attribute\Money;
use IanFoxDev\MoneyLint\MoneyDetector;
use PHPStan\Testing\PHPStanTestCase;

/**
 * extension.neon loads in PHPStan, and project settings add to the defaults.
 */
final class ExtensionConfigTest extends PHPStanTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/config/patterns.neon'];
    }

    public function testProjectSettingsReachTheDetector(): void
    {
        $detector = self::getContainer()->getByType(MoneyDetector::class);

        self::assertTrue($detector->isMoneyName('refundAmount'));
        self::assertTrue($detector->isMoneyDeclaration('total', [Money::class]));
        self::assertTrue($detector->isMoneyDeclaration('total', ['App\\Domain\\Cash']));
    }
}
