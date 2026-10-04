<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Transaction;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Jobs and mails queued in a callback of a call named like a transaction method,
 * without waiting for the commit.
 *
 * @implements Collector<CallLike, array{int, string, int}>
 */
final class DispatchCollector implements Collector
{
    public function __construct(private readonly QueuedDispatch $dispatch) {}

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $transaction = $node->getAttribute(TransactionCallbackVisitor::INSIDE);
        if (!is_int($transaction)) {
            return null;
        }
        $queued = $this->dispatch->describe($node, $scope);

        return $queued === null ? null : [$transaction, $queued, $node->getStartLine()];
    }
}
