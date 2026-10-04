<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Tests\Rules\Transaction;

use IanFoxDev\MoneyLint\Rules\Transaction\DispatchCollector;
use IanFoxDev\MoneyLint\Rules\Transaction\DispatchInTransactionRule;
use IanFoxDev\MoneyLint\Rules\Transaction\TransactionCallCollector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<DispatchInTransactionRule>
 */
final class DispatchInTransactionRuleTest extends RuleTestCase
{
    private const TIP = 'Chain ->afterCommit(), implement ShouldQueueAfterCommit, or set after_commit on the queue connection and money.queueAfterCommit: true. See https://github.com/IanFoxDev/money-lint/blob/master/docs/rules/dispatch-in-transaction.md';

    private bool $queueAfterCommit = false;

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../config/patterns.neon'];
    }

    protected function getRule(): Rule
    {
        return new DispatchInTransactionRule($this->queueAfterCommit);
    }

    protected function getCollectors(): array
    {
        return [
            self::getContainer()->getByType(TransactionCallCollector::class),
            self::getContainer()->getByType(DispatchCollector::class),
        ];
    }

    public function testQueuedWithoutAfterCommit(): void
    {
        $this->analyse([__DIR__ . '/data/jobs.php'], [
            [self::message('job CreditWallet'), 51, self::TIP],
            [self::message('job CreditWallet'), 52, self::TIP],
            [self::message('job CreditWallet'), 53, self::TIP],
            [self::message('job CreditWallet'), 54, self::TIP],
            [self::message('mail Receipt'), 55, self::TIP],
            [self::message('mail Receipt'), 56, self::TIP],
            [self::message('job CreditWallet'), 57, self::TIP],
            [self::message('closure'), 58, self::TIP],
        ]);
    }

    public function testSilentWhenQueuesWaitForCommit(): void
    {
        $this->queueAfterCommit = true;

        $this->analyse([__DIR__ . '/data/jobs.php'], []);
    }

    private static function message(string $queued): string
    {
        return sprintf(
            'The %s is queued inside DB::transaction(): a worker can run it before the commit and miss the data, or after a rollback.',
            $queued,
        );
    }
}
