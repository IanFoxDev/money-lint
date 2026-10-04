<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Transaction;

use PHPStan\Node\CollectedDataNode;

/**
 * @internal
 */
final class Transactions
{
    /**
     * Confirmed transaction calls: file => number => call as written.
     *
     * @return array<string, array<int, string>>
     */
    public static function confirmed(CollectedDataNode $node): array
    {
        $transactions = [];
        foreach ($node->get(TransactionCallCollector::class) as $file => $calls) {
            foreach ($calls as [$number, $call]) {
                $transactions[$file][$number] = $call;
            }
        }

        return $transactions;
    }
}
