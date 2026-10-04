<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Transaction;

use IanFoxDev\MoneyLint\CallPatterns;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Side effects (HTTP, email, brokers) in a callback of a call named like a
 * transaction method.
 *
 * @implements Collector<CallLike, array{int, string, int}>
 */
final class SideEffectCollector implements Collector
{
    public function __construct(
        private readonly CallPatterns $sideEffects,
        private readonly CallDescriber $describer,
    ) {}

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
        $call = $this->describer->match($node, $this->sideEffects, $scope);

        return $call === null ? null : [$transaction, $call, $node->getStartLine()];
    }
}
