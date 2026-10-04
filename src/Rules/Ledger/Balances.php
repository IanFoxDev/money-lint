<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Ledger;

use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * Balances that only the ledger may change, and the classes that are the ledger.
 *
 * A balance is 'App\Models\Wallet::balance' (a model or entity and its column or
 * property) or 'wallets.balance' (a table and its column, for the query builder and
 * SQL).
 *
 * @internal
 */
final class Balances
{
    /** @var array<string, list<string>> class => lowercase columns */
    private readonly array $classes;

    /** @var array<string, list<string>> lowercase table => lowercase columns */
    private readonly array $tables;

    /** @var list<string> */
    private readonly array $writers;

    /**
     * @param list<string> $balances
     * @param list<string> $writers
     */
    public function __construct(array $balances, array $writers)
    {
        $classes = [];
        $tables = [];
        foreach ($balances as $balance) {
            if (str_contains($balance, '::')) {
                [$class, $column] = explode('::', $balance, 2);
                $classes[ltrim($class, '\\')][] = strtolower(ltrim($column, '$'));
            } elseif (substr_count($balance, '.') === 1) {
                [$table, $column] = explode('.', $balance, 2);
                $tables[strtolower($table)][] = strtolower($column);
            } else {
                throw new \InvalidArgumentException(sprintf('Expected "Class::column" or "table.column" in money.balances, got "%s".', $balance));
            }
        }
        $this->classes = $classes;
        $this->tables = $tables;
        $this->writers = array_map(static fn(string $writer): string => ltrim($writer, '\\'), $writers);
    }

    public function isEmpty(): bool
    {
        return $this->classes === [] && $this->tables === [];
    }

    /**
     * The listed class the value is an instance of, with its balance columns.
     *
     * @return array{string, list<string>}|null
     */
    public function forType(Type $type): ?array
    {
        $type = TypeCombinator::removeNull($type);
        foreach ($this->classes as $class => $columns) {
            if ((new ObjectType($class))->isSuperTypeOf($type)->yes()) {
                return [$class, $columns];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function columnsOfClass(string $class): array
    {
        foreach ($this->classes as $listed => $columns) {
            if (strcasecmp($listed, ltrim($class, '\\')) === 0) {
                return $columns;
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public function columnsOfTable(string $table): array
    {
        $table = strtolower($table);
        $dot = strrpos($table, '.');

        return $this->tables[$dot === false ? $table : substr($table, $dot + 1)] ?? [];
    }

    public function isWriter(Scope $scope): bool
    {
        $class = $scope->getClassReflection();
        if ($class === null) {
            return false;
        }
        foreach ($this->writers as $writer) {
            if ($class->is($writer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Who may change the balances, for messages.
     */
    public function describeWriters(): string
    {
        if ($this->writers === []) {
            return 'the ledger (list it in money.balanceWriters)';
        }

        return implode(', ', $this->writers);
    }
}
