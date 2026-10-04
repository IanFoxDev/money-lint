<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Race;

use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;

/**
 * Recognises a row loaded from the database, and whether it was locked.
 *
 * @internal
 */
final class Loads
{
    private const MODEL = 'Illuminate\Database\Eloquent\Model';

    private const ELOQUENT_LOADS = ['find', 'findorfail', 'findor', 'first', 'firstorfail', 'firstwhere', 'sole'];

    private const ELOQUENT_LOCKS = ['lockforupdate', 'sharedlock', 'lock'];

    private const ENTITY_MANAGER = 'Doctrine\Persistence\ObjectManager';

    private const REPOSITORY = 'Doctrine\Persistence\ObjectRepository';

    private const VERSION = 'Doctrine\ORM\Mapping\Version';

    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    /**
     * The class of the loaded row and whether the load locked it; null when the
     * expression is not a load the rule knows, or the row has optimistic locking.
     *
     * @return array{string, bool}|null
     */
    public function describe(Expr $expr, Scope $scope): ?array
    {
        return $this->eloquent($expr, $scope) ?? $this->doctrine($expr, $scope);
    }

    /**
     * Wallet::find($id), Wallet::where(...)->first(), Wallet::query()->lockForUpdate()->find($id).
     *
     * @return array{string, bool}|null
     */
    private function eloquent(Expr $expr, Scope $scope): ?array
    {
        if (!($expr instanceof Expr\MethodCall || $expr instanceof Expr\StaticCall) || !$expr->name instanceof Identifier
            || !in_array($expr->name->toLowerString(), self::ELOQUENT_LOADS, true)) {
            return null;
        }
        $locked = false;
        while ($expr instanceof Expr\MethodCall) {
            $expr = $expr->var;
            if ($expr instanceof Expr\MethodCall || $expr instanceof Expr\StaticCall) {
                $locked = $locked || ($expr->name instanceof Identifier && in_array($expr->name->toLowerString(), self::ELOQUENT_LOCKS, true));
            }
        }
        if (!$expr instanceof Expr\StaticCall || !$expr->class instanceof Name) {
            return null;
        }
        $className = $scope->resolveName($expr->class);
        if (!$this->reflectionProvider->hasClass($className)) {
            return null;
        }
        $class = $this->reflectionProvider->getClass($className);

        return $class->is(self::MODEL) ? [$class->getName(), $locked] : null;
    }

    /**
     * $em->find(Wallet::class, $id), $repository->find($id), $repository->findOneBy([...]).
     *
     * @return array{string, bool}|null
     */
    private function doctrine(Expr $expr, Scope $scope): ?array
    {
        if (!$expr instanceof Expr\MethodCall || !$expr->name instanceof Identifier) {
            return null;
        }
        $method = $expr->name->toLowerString();
        $receiver = $scope->getType($expr->var);
        $args = count($expr->getArgs());
        if ($method === 'find' && (new ObjectType(self::ENTITY_MANAGER))->isSuperTypeOf($receiver)->yes()) {
            $locked = $args >= 3;
        } elseif ($method === 'find' && (new ObjectType(self::REPOSITORY))->isSuperTypeOf($receiver)->yes()) {
            $locked = $args >= 2;
        } elseif ($method === 'findoneby' && (new ObjectType(self::REPOSITORY))->isSuperTypeOf($receiver)->yes()) {
            $locked = false;
        } else {
            return null;
        }
        $classes = TypeCombinator::removeNull($scope->getType($expr))->getObjectClassReflections();
        if (count($classes) !== 1) {
            return null;
        }
        $class = $classes[0];
        // A version column makes Doctrine detect the lost update and throw.
        foreach ($class->getNativeReflection()->getProperties() as $property) {
            if ($property->getAttributes(self::VERSION) !== []) {
                return null;
            }
        }

        return [$class->getName(), $locked];
    }
}
