<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Transaction;

use IanFoxDev\MoneyLint\Rules\RuleDocs;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * An HTTP request, an email or a message to a broker sent from inside a database
 * transaction.
 *
 * @implements Rule<CollectedDataNode>
 */
final class SideEffectInTransactionRule implements Rule
{
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $transactions = Transactions::confirmed($node);

        $errors = [];
        foreach ($node->get(SideEffectCollector::class) as $file => $sideEffects) {
            foreach ($sideEffects as [$number, $call, $line]) {
                $transaction = $transactions[$file][$number] ?? null;
                if ($transaction === null) {
                    continue;
                }
                $errors[] = RuleErrorBuilder::message(sprintf(
                    '%s runs inside %s: it cannot be rolled back with the transaction, and the transaction stays open while it waits.',
                    $call,
                    $transaction,
                ))
                    ->file($file)
                    ->line($line)
                    ->identifier('money.sideEffectInTransaction')
                    ->tip(RuleDocs::tip('side-effect-in-transaction', 'Make the call after commit, or record it in the transaction (an outbox row) and send it from there.'))
                    ->build();
            }
        }

        return $errors;
    }
}
