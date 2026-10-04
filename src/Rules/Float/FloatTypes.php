<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Float;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * @internal
 */
final class FloatTypes
{
    public const IDENTIFIER = 'money.float';

    public const TIP = 'Keep amounts as integers in minor units, decimal strings, or money objects.';

    /**
     * Whether a declared native type names float: float, ?float, int|float.
     */
    public static function nativeTypeNamesFloat(?Node $type): bool
    {
        if ($type instanceof Identifier) {
            return $type->toLowerString() === 'float';
        }
        if ($type instanceof NullableType) {
            return self::nativeTypeNamesFloat($type->type);
        }
        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            foreach ($type->types as $inner) {
                if (self::nativeTypeNamesFloat($inner)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a PHPDoc type is float or nullable float. Wider unions are left alone.
     */
    public static function phpDocTypeIsFloat(?Type $type): bool
    {
        return $type !== null && TypeCombinator::removeNull($type)->isFloat()->yes();
    }
}
