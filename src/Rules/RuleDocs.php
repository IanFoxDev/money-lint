<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules;

/**
 * Links from error tips to the page that explains a rule.
 */
final class RuleDocs
{
    private const BASE = 'https://github.com/IanFoxDev/money-lint/blob/master/docs/rules/';

    public static function tip(string $page, string $fix): string
    {
        return $fix . ' See ' . self::BASE . $page . '.md';
    }
}
