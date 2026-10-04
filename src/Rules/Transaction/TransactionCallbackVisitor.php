<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Transaction;

use IanFoxDev\MoneyLint\CallPatterns;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeVisitorAbstract;

/**
 * Marks the calls that run inside a callback passed to a method named like a
 * transaction method (transaction(), wrapInTransaction(), ...).
 *
 * This works on names only, before types are known. TransactionCallCollector later
 * checks the type of the receiver, so $cache->transaction(fn) does not count.
 *
 * A call stays marked through callbacks that PHP runs right away (array_map() and
 * similar). Any other callback inside the transaction, afterCommit() included, may run
 * later, so the calls in it are not marked.
 */
final class TransactionCallbackVisitor extends NodeVisitorAbstract
{
    /** Set on a transaction call: its number in the file. */
    public const TRANSACTION = 'moneyLintTransaction';

    /** Set on a call inside a transaction callback: the number of that transaction call. */
    public const INSIDE = 'moneyLintInsideTransaction';

    private const IMMEDIATE_FUNCTIONS = [
        'array_map' => true,
        'array_filter' => true,
        'array_walk' => true,
        'array_walk_recursive' => true,
        'array_reduce' => true,
        'usort' => true,
        'uasort' => true,
        'uksort' => true,
        'iterator_apply' => true,
        'call_user_func' => true,
        'call_user_func_array' => true,
    ];

    /** @var array<string, true> */
    private readonly array $transactionMethods;

    /** @var list<int|null> transaction numbers, null where a callback may run later */
    private array $stack = [];

    /** @var array<int, int|null> what a callback inherits, by spl_object_id() */
    private array $callbacks = [];

    private int $next = 0;

    public function __construct(CallPatterns $transactions)
    {
        $this->transactionMethods = array_fill_keys($transactions->methodNames(), true);
    }

    public function beforeTraverse(array $nodes): ?array
    {
        $this->stack = [null];
        $this->callbacks = [];
        $this->next = 0;

        return null;
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Expr\Closure || $node instanceof Expr\ArrowFunction) {
            $this->stack[] = $this->callbacks[spl_object_id($node)] ?? null;

            return null;
        }
        if ($node instanceof Node\FunctionLike || $node instanceof Node\Stmt\ClassLike) {
            $this->stack[] = null;

            return null;
        }
        if (!$node instanceof Expr\CallLike || $node->isFirstClassCallable()) {
            return null;
        }

        $current = $this->stack[count($this->stack) - 1];
        if ($current !== null) {
            $node->setAttribute(self::INSIDE, $current);
        }

        $passes = null;
        if ($this->isTransactionCall($node)) {
            $passes = $this->next++;
            $node->setAttribute(self::TRANSACTION, $passes);
        } elseif ($node instanceof Expr\FuncCall && $node->name instanceof Name
            && isset(self::IMMEDIATE_FUNCTIONS[$node->name->toLowerString()])) {
            $passes = $current;
        }
        foreach ($node->getArgs() as $arg) {
            if ($arg->value instanceof Expr\Closure || $arg->value instanceof Expr\ArrowFunction) {
                $this->callbacks[spl_object_id($arg->value)] = $passes;
            }
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Node\FunctionLike || $node instanceof Node\Stmt\ClassLike) {
            array_pop($this->stack);
        }

        return null;
    }

    private function isTransactionCall(Expr\CallLike $node): bool
    {
        if (!$node instanceof Expr\MethodCall && !$node instanceof Expr\NullsafeMethodCall && !$node instanceof Expr\StaticCall) {
            return false;
        }

        return $node->name instanceof Identifier && isset($this->transactionMethods[$node->name->toLowerString()]);
    }
}
