<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Tests\Rules\Transaction;

use IanFoxDev\MoneyLint\Rules\Transaction\SideEffectCollector;
use IanFoxDev\MoneyLint\Rules\Transaction\SideEffectInTransactionRule;
use IanFoxDev\MoneyLint\Rules\Transaction\TransactionCallCollector;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<SideEffectInTransactionRule>
 */
final class SideEffectInTransactionRuleTest extends RuleTestCase
{
    private const TIP = 'Make the call after commit, or record it in the transaction (an outbox row) and send it from there. See https://github.com/IanFoxDev/money-lint/blob/master/docs/rules/side-effect-in-transaction.md';

    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../../config/patterns.neon'];
    }

    protected function getRule(): Rule
    {
        return new SideEffectInTransactionRule();
    }

    protected function getCollectors(): array
    {
        return [
            self::getContainer()->getByType(TransactionCallCollector::class),
            self::getContainer()->getByType(SideEffectCollector::class),
        ];
    }

    public function testLaravel(): void
    {
        $this->analyse([__DIR__ . '/data/laravel.php'], [
            [self::message('Http::post()', 'DB::transaction()'), 27, self::TIP],
            [self::message('Mail::raw()', 'DB::transaction()'), 28, self::TIP],
            [self::message('$psp->post()', '$db->transaction()'), 32, self::TIP],
            [self::message("\\Illuminate\\Support\\Facades\\Http::withToken('secret')->post()", '$db->transaction()'), 33, self::TIP],
            [self::message('Http::get()', 'DB::transaction()'), 36, self::TIP],
            [self::message('Http::post()', 'DB::transaction()'), 39, self::TIP],
            [self::message('Http::post()', 'DB::transaction()'), 43, self::TIP],
        ]);
    }

    public function testClientsAndDoctrine(): void
    {
        $this->analyse([__DIR__ . '/data/clients.php'], [
            [self::message('$psr->sendRequest()', '$em->wrapInTransaction()'), 35, self::TIP],
            [self::message('$symfony->request()', '$em->wrapInTransaction()'), 36, self::TIP],
            [self::message('$mailer->send()', '$em->wrapInTransaction()'), 37, self::TIP],
            // Reported after a void call too: PHPStan's own call stack loses the
            // transaction there, which is why the rule marks callbacks itself.
            [self::message('$guzzle->post()', '$em->wrapInTransaction()'), 38, self::TIP],
            [self::message('$channel->basic_publish()', '$em->wrapInTransaction()'), 39, self::TIP],
            [self::message('$topic->produce()', '$em->wrapInTransaction()'), 40, self::TIP],
            [self::message('$stripe->refunds->create()', '$em->wrapInTransaction()'), 41, self::TIP],
            [self::message('$guzzle->request()', '$connection->transactional()'), 49, self::TIP],
        ]);
    }

    private static function message(string $call, string $transaction): string
    {
        return sprintf(
            '%s runs inside %s: it cannot be rolled back with the transaction, and the transaction stays open while it waits.',
            $call,
            $transaction,
        );
    }
}
