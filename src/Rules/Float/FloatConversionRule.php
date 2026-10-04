<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Float;

use IanFoxDev\MoneyLint\MoneyExpressions;
use IanFoxDev\MoneyLint\Rules\RuleDocs;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Float_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Money turned into a float: (float), floatval(), round() to decimal places, or a
 * float literal written into a money property. A conversion that is only compared
 * with zero or checked for truth is left alone (see ZeroCheckVisitor).
 *
 * @implements Rule<Expr>
 */
final class FloatConversionRule implements Rule
{
    public function __construct(private readonly MoneyExpressions $money) {}

    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->getAttribute(ZeroCheckVisitor::ZERO_CHECK) === true) {
            return [];
        }
        if ($node instanceof Expr\Cast\Double && $this->money->isMoney($node->expr, $scope)) {
            return [$this->error(sprintf('Money %s is cast to float.', $this->money->describe($node->expr)))];
        }

        if ($node instanceof Expr\FuncCall && $node->name instanceof Name) {
            return $this->functionCall($node, $node->name->toLowerString(), $scope);
        }

        if (($node instanceof Expr\Assign || $node instanceof Expr\AssignOp)
            && $this->isFloatLiteral($node->expr)
            && $this->money->isMoney($node->var, $scope)) {
            return [$this->error(sprintf('Float literal is written into money %s.', $this->money->describe($node->var)))];
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function functionCall(Expr\FuncCall $call, string $function, Scope $scope): array
    {
        $args = array_values(array_filter($call->getArgs(), static fn(Arg $arg): bool => $arg->name === null));
        $value = $args[0] ?? null;
        if ($value === null || !$this->money->isMoney($value->value, $scope)) {
            return [];
        }

        if ($function === 'floatval' || $function === 'doubleval') {
            return [$this->error(sprintf('Money %s is converted to float by %s().', $this->money->describe($value->value), $function))];
        }

        if ($function === 'round' && $this->roundsToDecimalPlaces($call, $args, $scope)) {
            return [$this->error(sprintf(
                'Money %s is rounded to decimal places, so it is held as a float.',
                $this->money->describe($value->value),
            ))];
        }

        return [];
    }

    /**
     * @param list<Arg> $positional
     */
    private function roundsToDecimalPlaces(Expr\FuncCall $call, array $positional, Scope $scope): bool
    {
        $precision = $positional[1] ?? null;
        foreach ($call->getArgs() as $arg) {
            if ($arg->name !== null && $arg->name->toLowerString() === 'precision') {
                $precision = $arg;
            }
        }
        if ($precision === null) {
            return false;
        }
        $values = $scope->getType($precision->value)->getConstantScalarValues();
        if ($values === []) {
            return false;
        }
        foreach ($values as $places) {
            if (!is_int($places) || $places < 1) {
                return false;
            }
        }

        return true;
    }

    private function isFloatLiteral(Expr $expr): bool
    {
        if ($expr instanceof Expr\UnaryMinus || $expr instanceof Expr\UnaryPlus) {
            $expr = $expr->expr;
        }

        return $expr instanceof Float_;
    }

    private function error(string $message): IdentifierRuleError
    {
        return RuleErrorBuilder::message($message)
            ->identifier(FloatTypes::IDENTIFIER)
            ->tip(RuleDocs::tip('float', FloatTypes::TIP))
            ->build();
    }
}
