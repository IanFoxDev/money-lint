<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Race;

use IanFoxDev\MoneyLint\MoneyExpressions;
use IanFoxDev\MoneyLint\Types;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * Facts for money.checkThenAct, per function:
 *
 * - ['assign', function, variable, line, class, locked]: a variable gets a value; class
 *   is the loaded row's class, or '' when the value is not a load;
 * - ['write', function, variable, property, line, money]: a property of the variable
 *   is changed from its own old value;
 * - ['persist', function, variable, line]: save() on the variable, or '' for flush().
 *
 * @implements Collector<Expr, array{string, int, string, int|string, int|string|bool, bool|string}>
 */
final class CheckThenActCollector implements Collector
{
    private const SAVES = ['save', 'saveorfail', 'savequietly', 'push', 'update', 'updateorfail', 'updatequietly'];

    private const OBJECT_MANAGER = 'Doctrine\Persistence\ObjectManager';

    private readonly NodeFinder $finder;

    public function __construct(
        private readonly Loads $loads,
        private readonly MoneyExpressions $money,
    ) {
        $this->finder = new NodeFinder();
    }

    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): ?array
    {
        $function = $node->getAttribute(FunctionNumberVisitor::FUNCTION);
        if (!is_int($function)) {
            return null;
        }
        $line = $node->getStartLine();

        if ($node instanceof Expr\Assign && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
            $load = $this->loads->describe($node->expr, $scope);

            return ['assign', $function, $node->var->name, $line, $load[0] ?? '', $load[1] ?? false];
        }

        $target = match (true) {
            $node instanceof Expr\AssignOp, $node instanceof Expr\Assign => $node->var,
            $node instanceof Expr\PreInc, $node instanceof Expr\PostInc,
            $node instanceof Expr\PreDec, $node instanceof Expr\PostDec => $node->var,
            default => null,
        };
        if ($target instanceof Expr\PropertyFetch && $target->var instanceof Expr\Variable
            && is_string($target->var->name) && $target->name instanceof Identifier) {
            if ($node instanceof Expr\Assign && !$this->reads($node->expr, $target->var->name, $target->name->toString())) {
                // Overwritten with a value that does not depend on the old one.
                return null;
            }

            return ['write', $function, $target->var->name, $target->name->toString(), $line, $this->money->isMoney($target, $scope)];
        }

        if ($node instanceof Expr\MethodCall && $node->name instanceof Identifier) {
            $method = $node->name->toLowerString();
            if (in_array($method, self::SAVES, true) && $node->var instanceof Expr\Variable && is_string($node->var->name)) {
                return ['persist', $function, $node->var->name, $line, '', ''];
            }
            if ($method === 'flush' && Types::isInstanceOf($scope->getType($node->var), self::OBJECT_MANAGER)) {
                return ['persist', $function, '', $line, '', ''];
            }
        }

        return null;
    }

    private function reads(Expr $expr, string $variable, string $property): bool
    {
        return $this->finder->findFirst($expr, static fn(Node $node): bool => $node instanceof Expr\PropertyFetch
            && $node->var instanceof Expr\Variable && $node->var->name === $variable
            && $node->name instanceof Identifier && $node->name->toString() === $property) !== null;
    }
}
