<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint;

use PhpParser\Node\AttributeGroup;

/**
 * Decides whether a property or parameter holds money: by an attribute or by a name
 * pattern from the configuration. See docs/adr/0001-what-counts-as-money.md.
 */
final class MoneyDetector
{
    /** @var list<string> */
    private readonly array $patterns;

    /** @var array<string, true> lowercase class names without a leading backslash */
    private readonly array $attributes;

    /**
     * @param list<string> $properties fnmatch() patterns, matched case-insensitively
     * @param list<string> $attributes attribute class names that mark money
     */
    public function __construct(array $properties, array $attributes)
    {
        $this->patterns = array_map(strtolower(...), $properties);
        $marks = [];
        foreach ($attributes as $class) {
            $marks[strtolower(ltrim($class, '\\'))] = true;
        }
        $this->attributes = $marks;
    }

    public function isMoneyName(string $name): bool
    {
        $name = strtolower($name);
        foreach ($this->patterns as $pattern) {
            if (fnmatch($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $attributeClasses resolved class names of the attributes on the declaration
     */
    public function isMoneyDeclaration(string $name, array $attributeClasses): bool
    {
        foreach ($attributeClasses as $class) {
            if (isset($this->attributes[strtolower(ltrim($class, '\\'))])) {
                return true;
            }
        }

        return $this->isMoneyName($name);
    }

    /**
     * Class names of the attributes in the groups, as the name resolver left them.
     *
     * @param array<AttributeGroup> $groups
     *
     * @return list<string>
     */
    public static function attributeClasses(array $groups): array
    {
        $classes = [];
        foreach ($groups as $group) {
            foreach ($group->attrs as $attribute) {
                $classes[] = $attribute->name->toString();
            }
        }

        return $classes;
    }
}
