<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Float;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\NodeVisitorAbstract;

/**
 * Marks (float) and floatval() whose result is only checked against zero or for
 * truth: (float) $amount > 0, ! (float) $discount, floatval($balance) == 0. A float
 * keeps the sign and the zero of a decimal amount exactly, so these are not reported.
 */
final class ZeroCheckVisitor extends NodeVisitorAbstract
{
    public const ZERO_CHECK = 'moneyLintZeroCheck';

    private const COMPARISONS = [
        BinaryOp\Greater::class,
        BinaryOp\GreaterOrEqual::class,
        BinaryOp\Smaller::class,
        BinaryOp\SmallerOrEqual::class,
        BinaryOp\Equal::class,
        BinaryOp\NotEqual::class,
        BinaryOp\Identical::class,
        BinaryOp\NotIdentical::class,
    ];

    public function enterNode(Node $node): null
    {
        if ($node instanceof Expr\BooleanNot) {
            $this->mark($node->expr);
        } elseif ($node instanceof Stmt\If_ || $node instanceof Stmt\ElseIf_ || $node instanceof Stmt\While_) {
            $this->mark($node->cond);
        } elseif ($node instanceof Expr\Ternary) {
            $this->mark($node->cond);
        } elseif ($node instanceof BinaryOp\BooleanAnd || $node instanceof BinaryOp\BooleanOr
            || $node instanceof BinaryOp\LogicalAnd || $node instanceof BinaryOp\LogicalOr) {
            $this->mark($node->left);
            $this->mark($node->right);
        } elseif ($node instanceof BinaryOp && in_array($node::class, self::COMPARISONS, true)) {
            if ($this->isZero($node->right)) {
                $this->mark($node->left);
            } elseif ($this->isZero($node->left)) {
                $this->mark($node->right);
            }
        }

        return null;
    }

    private function mark(Expr $expr): void
    {
        if ($expr instanceof Expr\Cast\Double || $expr instanceof Expr\FuncCall) {
            $expr->setAttribute(self::ZERO_CHECK, true);
        }
    }

    private function isZero(Expr $expr): bool
    {
        return ($expr instanceof Scalar\Int_ || $expr instanceof Scalar\Float_) && $expr->value == 0;
    }
}
