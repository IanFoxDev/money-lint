<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Tests\Rules\Ledger;

use IanFoxDev\MoneyLint\Rules\Ledger\BalanceOutsideLedgerRule;
use IanFoxDev\MoneyLint\Rules\Ledger\Balances;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<BalanceOutsideLedgerRule>
 */
final class BalanceOutsideLedgerRuleTest extends RuleTestCase
{
    private const TIP = 'Post an entry through the ledger and let it update the balance in the same transaction. See https://github.com/IanFoxDev/money-lint/blob/master/docs/rules/balance-outside-ledger.md';

    private ?Balances $balances = null;

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../config/balances.neon'];
    }

    protected function getRule(): Rule
    {
        return new BalanceOutsideLedgerRule($this->balances ?? self::getContainer()->getByType(Balances::class));
    }

    public function testBalanceChangedOutsideTheLedger(): void
    {
        $wallet = 'LedgerFixtures\Wallet::$balance';
        $account = 'LedgerFixtures\Account::$balance';

        $this->analyse([__DIR__ . '/data/ledger.php'], [
            [self::message($account, 'an assignment'), 29, self::TIP],
            [self::message($wallet, 'an assignment'), 52, self::TIP],
            [self::message($wallet, 'an assignment'), 53, self::TIP],
            [self::message($wallet, 'increment()'), 54, self::TIP],
            [self::message($wallet, 'decrement()'), 55, self::TIP],
            [self::message($wallet, 'incrementEach()'), 56, self::TIP],
            [self::message($wallet, 'update()'), 57, self::TIP],
            [self::message($wallet, 'increment()'), 58, self::TIP],
            [self::message('wallets.balance', 'increment()'), 59, self::TIP],
            [self::message('wallets.balance', 'update()'), 60, self::TIP],
            [self::message('wallets.balance', 'an SQL UPDATE'), 61, self::TIP],
            [self::message($account, 'a DQL UPDATE'), 62, self::TIP],
            [self::message($account, 'setBalance()'), 63, self::TIP],
            [self::message($wallet, 'increment()'), 64, self::TIP],
            [self::message($wallet, 'an assignment'), 66, self::TIP],
        ]);
    }

    public function testSilentWithoutBalancesConfigured(): void
    {
        $this->balances = new Balances([], []);

        $this->analyse([__DIR__ . '/data/ledger.php'], []);
    }

    private static function message(string $balance, string $how): string
    {
        return sprintf(
            'Balance %s is changed outside the ledger by %s. Only LedgerFixtures\Ledger may change it.',
            $balance,
            $how,
        );
    }
}
