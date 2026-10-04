<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\VarLikeIdentifier;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedPropertyReflection;

/**
 * Tells whether an expression reads money: a property marked as money, or a
 * parameter of the current function marked as money. Other expressions, local
 * variables included, are not money as far as the rules know.
 */
final class MoneyExpressions
{
    private readonly Standard $printer;

    public function __construct(private readonly MoneyDetector $detector)
    {
        $this->printer = new Standard();
    }

    public function isMoney(Expr $expr, Scope $scope): bool
    {
        if ($expr instanceof Expr\PropertyFetch || $expr instanceof Expr\NullsafePropertyFetch) {
            if (!$expr->name instanceof Identifier) {
                return false;
            }
            $name = $expr->name->toString();

            return $this->detector->isMoneyName($name)
                || $this->hasMoneyAttribute($scope->getPropertyReflection($scope->getType($expr->var), $name));
        }

        if ($expr instanceof Expr\StaticPropertyFetch) {
            if (!$expr->name instanceof VarLikeIdentifier) {
                return false;
            }
            $name = $expr->name->toString();
            if ($this->detector->isMoneyName($name)) {
                return true;
            }

            return $expr->class instanceof Name
                && $this->hasMoneyAttribute($scope->getPropertyReflection($scope->resolveTypeByName($expr->class), $name));
        }

        if ($expr instanceof Expr\Variable && is_string($expr->name)) {
            $function = $scope->getFunction();
            if ($function === null) {
                return false;
            }
            foreach ($function->getParameters() as $parameter) {
                if ($parameter->getName() !== $expr->name) {
                    continue;
                }
                $attributes = [];
                foreach ($parameter->getAttributes() as $attribute) {
                    $attributes[] = $attribute->getName();
                }

                return $this->detector->isMoneyDeclaration($parameter->getName(), $attributes);
            }
        }

        return false;
    }

    /**
     * Source code of the expression, for error messages.
     */
    public function describe(Expr $expr): string
    {
        return $this->printer->prettyPrintExpr($expr);
    }

    private function hasMoneyAttribute(?ExtendedPropertyReflection $property): bool
    {
        if ($property === null) {
            return false;
        }
        $attributes = [];
        foreach ($property->getAttributes() as $attribute) {
            $attributes[] = $attribute->getName();
        }

        return $this->detector->hasMoneyAttribute($attributes);
    }
}
