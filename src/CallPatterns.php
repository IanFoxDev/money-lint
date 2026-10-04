<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint;

use PHPStan\Reflection\ClassReflection;
use PHPStan\Type\Type;

/**
 * A list of 'Class::method' patterns from the configuration. The class matches
 * itself and its subtypes; the method is a name, several names joined with '|', or '*'.
 */
final class CallPatterns
{
    /** @var array<string, array<string, true>> class name => lowercase method names, '*' for any */
    private readonly array $patterns;

    /**
     * @param list<string> $patterns
     */
    public function __construct(array $patterns)
    {
        $parsed = [];
        foreach ($patterns as $pattern) {
            $parts = explode('::', $pattern, 2);
            if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
                throw new \InvalidArgumentException(sprintf('Expected "Class::method", "Class::a|b" or "Class::*", got "%s".', $pattern));
            }
            foreach (explode('|', $parts[1]) as $method) {
                $parsed[ltrim($parts[0], '\\')][strtolower($method)] = true;
            }
        }
        $this->patterns = $parsed;
    }

    /**
     * Lowercase method names in the patterns, without '*'.
     *
     * @return list<string>
     */
    public function methodNames(): array
    {
        $names = [];
        foreach ($this->patterns as $methods) {
            foreach (array_keys($methods) as $method) {
                if ($method !== '*') {
                    $names[$method] = true;
                }
            }
        }

        return array_keys($names);
    }

    /**
     * For a method called on a value of this type.
     */
    public function matchesType(Type $type, string $method): bool
    {
        $method = strtolower($method);
        foreach ($this->patterns as $class => $methods) {
            if ((isset($methods['*']) || isset($methods[$method])) && Types::isInstanceOf($type, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * For a method of a class, as found on the call stack or in a static call.
     */
    public function matchesClass(ClassReflection $class, string $method): bool
    {
        return $this->matchesClassName($class->getName(), $method, $class);
    }

    /**
     * For a static call on a class name that may not be known to reflection, such as
     * Laravel's global facade aliases (\DB, \Http).
     */
    public function matchesClassName(string $className, string $method, ?ClassReflection $class = null): bool
    {
        $method = strtolower($method);
        $className = ltrim($className, '\\');
        foreach ($this->patterns as $pattern => $methods) {
            if (!isset($methods['*']) && !isset($methods[$method])) {
                continue;
            }
            if (strcasecmp($pattern, $className) === 0 || ($class !== null && $class->is($pattern))) {
                return true;
            }
        }

        return false;
    }
}
