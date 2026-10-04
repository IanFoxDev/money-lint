<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Transaction;

use IanFoxDev\MoneyLint\CallPatterns;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;

/**
 * Matches a method call against call patterns and describes it as written, such as
 * "Http::post()" or "$em->wrapInTransaction()".
 *
 * @internal
 */
final class CallDescriber
{
    private readonly Standard $printer;

    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
        $this->printer = new Standard();
    }

    public function match(Expr\CallLike $node, CallPatterns $patterns, Scope $scope): ?string
    {
        if (($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) && $node->name instanceof Identifier) {
            $method = $node->name->toString();
            if ($patterns->matchesType($scope->getType($node->var), $method)) {
                return $this->printer->prettyPrintExpr($node->var) . ($node instanceof Expr\NullsafeMethodCall ? '?->' : '->') . $method . '()';
            }

            return null;
        }

        if ($node instanceof Expr\StaticCall && $node->class instanceof Name && $node->name instanceof Identifier) {
            $method = $node->name->toString();
            $className = $scope->resolveName($node->class);
            $class = $this->reflectionProvider->hasClass($className) ? $this->reflectionProvider->getClass($className) : null;
            if ($patterns->matchesClassName($className, $method, $class)) {
                return $node->class->getLast() . '::' . $method . '()';
            }
        }

        return null;
    }
}
