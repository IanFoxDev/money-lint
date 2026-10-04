<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Tests;

use IanFoxDev\MoneyLint\Attribute\Money;
use IanFoxDev\MoneyLint\MoneyDetector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function names(): iterable
    {
        yield 'exact' => ['balance', true];
        yield 'suffix' => ['totalAmount', true];
        yield 'case' => ['AMOUNT', true];
        yield 'snake case suffix' => ['refund_amount', true];
        yield 'pattern is anchored' => ['amountInWords', false];
        yield 'unrelated' => ['quantity', false];
    }

    #[DataProvider('names')]
    public function testNamePatterns(string $name, bool $money): void
    {
        $detector = new MoneyDetector(['*amount', 'balance'], []);

        self::assertSame($money, $detector->isMoneyName($name));
    }

    public function testNoPatternsMeansNoMoneyByName(): void
    {
        $detector = new MoneyDetector([], [Money::class]);

        self::assertFalse($detector->isMoneyName('amount'));
    }

    public function testAttributeMarksMoneyWhateverTheName(): void
    {
        $detector = new MoneyDetector([], [Money::class, 'App\\Domain\\Cash']);

        self::assertTrue($detector->isMoneyDeclaration('total', [Money::class]));
        self::assertTrue($detector->isMoneyDeclaration('total', ['\\app\\domain\\CASH']));
        self::assertFalse($detector->isMoneyDeclaration('total', ['App\\Domain\\Other']));
    }
}
