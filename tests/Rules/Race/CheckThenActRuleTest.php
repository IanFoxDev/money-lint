<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Tests\Rules\Race;

use IanFoxDev\MoneyLint\Rules\Ledger\Balances;
use IanFoxDev\MoneyLint\Rules\Race\CheckThenActCollector;
use IanFoxDev\MoneyLint\Rules\Race\CheckThenActRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<CheckThenActRule>
 */
final class CheckThenActRuleTest extends RuleTestCase
{
    private const TIP = 'Load it with lockForUpdate() (Doctrine: LockMode::PESSIMISTIC_WRITE) inside a transaction, or add a version column. See https://github.com/IanFoxDev/money-lint/blob/master/docs/rules/check-then-act.md';

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../config/patterns.neon'];
    }

    protected function getRule(): Rule
    {
        return new CheckThenActRule(self::getContainer()->getByType(Balances::class));
    }

    protected function getCollectors(): array
    {
        return [self::getContainer()->getByType(CheckThenActCollector::class)];
    }

    public function testLostUpdates(): void
    {
        $this->analyse([__DIR__ . '/data/race.php'], [
            [self::message('RaceFixtures\Wallet::$balance', 39), 40, self::TIP],
            [self::message('RaceFixtures\Wallet::$balance', 46), 47, self::TIP],
            [self::message('RaceFixtures\Account::$funds', 53), 54, self::TIP],
            [self::message('RaceFixtures\Account::$funds', 60), 61, self::TIP],
        ]);
    }

    private static function message(string $property, int $loadLine): string
    {
        return sprintf(
            '%s is read without a lock on line %d and written back from its old value: two concurrent requests read the same value, and one change is lost.',
            $property,
            $loadLine,
        );
    }
}
