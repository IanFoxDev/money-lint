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
 * A Laravel job or mail queued inside a database transaction, without afterCommit.
 *
 * @implements Rule<CollectedDataNode>
 */
final class DispatchInTransactionRule implements Rule
{
    public function __construct(private readonly bool $queueAfterCommit) {}

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->queueAfterCommit) {
            return [];
        }
        $transactions = Transactions::confirmed($node);

        $errors = [];
        foreach ($node->get(DispatchCollector::class) as $file => $dispatches) {
            foreach ($dispatches as [$number, $queued, $line]) {
                $transaction = $transactions[$file][$number] ?? null;
                if ($transaction === null) {
                    continue;
                }
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'The %s is queued inside %s: a worker can run it before the commit and miss the data, or after a rollback.',
                    $queued,
                    $transaction,
                ))
                    ->file($file)
                    ->line($line)
                    ->identifier('money.dispatchInTransaction')
                    ->tip(RuleDocs::tip('dispatch-in-transaction', 'Chain ->afterCommit(), implement ShouldQueueAfterCommit, or set after_commit on the queue connection and money.queueAfterCommit: true.'))
                    ->build();
            }
        }

        return $errors;
    }
}
