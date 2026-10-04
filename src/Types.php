<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint;

use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * @internal
 */
final class Types
{
    /**
     * Whether the value is surely an instance of the class, null aside.
     *
     * A type PHPStan could not work out (*NEVER* after an error, mixed) has no class
     * names and does not match: never is a subtype of every class, so a plain
     * isSuperTypeOf() would say yes to every pattern.
     */
    public static function isInstanceOf(Type $type, string $class): bool
    {
        $type = TypeCombinator::removeNull($type);
        if ($type->getObjectClassNames() === []) {
            return false;
        }

        return (new ObjectType($class))->isSuperTypeOf($type)->yes();
    }
}
