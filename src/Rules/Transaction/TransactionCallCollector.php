<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Transaction;

use IanFoxDev\MoneyLint\CallPatterns;
use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Transaction calls whose receiver really is a database connection or an entity
 * manager, by type.
 *
 * @implements Collector<CallLike, array{int, string}>
 */
final class TransactionCallCollector implements Collector
{
    public function __construct(
        private readonly CallPatterns $transactions,
        private readonly CallDescriber $describer,
    ) {}

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $number = $node->getAttribute(TransactionCallbackVisitor::TRANSACTION);
        if (!is_int($number)) {
            return null;
        }
        $call = $this->describer->match($node, $this->transactions, $scope);

        return $call === null ? null : [$number, $call];
    }
}
