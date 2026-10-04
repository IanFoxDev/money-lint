<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Race;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;

/**
 * Numbers the functions, methods and closures of a file and marks every expression
 * with the number of the one it belongs to, so collected facts can be joined per
 * function.
 */
final class FunctionNumberVisitor extends NodeVisitorAbstract
{
    public const FUNCTION = 'moneyLintFunction';

    /** @var list<int> */
    private array $stack = [];

    private int $next = 0;

    public function beforeTraverse(array $nodes): ?array
    {
        $this->stack = [];
        $this->next = 0;

        return null;
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Node\FunctionLike) {
            $this->stack[] = $this->next++;

            return null;
        }
        if ($node instanceof Node\Expr && $this->stack !== []) {
            $node->setAttribute(self::FUNCTION, $this->stack[count($this->stack) - 1]);
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Node\FunctionLike) {
            array_pop($this->stack);
        }

        return null;
    }
}
