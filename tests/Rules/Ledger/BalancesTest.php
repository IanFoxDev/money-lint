<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Tests\Rules\Ledger;

use IanFoxDev\MoneyLint\Rules\Ledger\Balances;
use PHPUnit\Framework\TestCase;

final class BalancesTest extends TestCase
{
    public function testTablesMatchWithAndWithoutSchema(): void
    {
        $balances = new Balances(['wallets.balance'], []);

        self::assertSame(['balance'], $balances->columnsOfTable('WALLETS'));
        self::assertSame(['balance'], $balances->columnsOfTable('billing.wallets'));
        self::assertSame([], $balances->columnsOfTable('orders'));
    }

    public function testRejectsAnEntryThatIsNeitherClassNorTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Balances(['balance'], []);
    }
}
