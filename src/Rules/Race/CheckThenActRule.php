<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Race;

use IanFoxDev\MoneyLint\Rules\Ledger\Balances;
use IanFoxDev\MoneyLint\Rules\RuleDocs;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Money read without a lock, changed from its old value and saved: a lost update
 * when two requests do it at once.
 *
 * @implements Rule<CollectedDataNode>
 */
final class CheckThenActRule implements Rule
{
    public function __construct(private readonly Balances $balances) {}

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @var array<string, array<int, list<array{string, int, string, int|string, int|string|bool, bool|string}>>> $facts */
        $facts = [];
        foreach ($node->get(CheckThenActCollector::class) as $file => $items) {
            foreach ($items as $item) {
                $facts[$file][$item[1]][] = $item;
            }
        }

        $errors = [];
        foreach ($facts as $file => $functions) {
            foreach ($functions as $items) {
                usort($items, static fn(array $a, array $b): int => self::line($a) <=> self::line($b));
                foreach ($this->lostUpdates($items) as [$class, $property, $loadLine, $writeLine]) {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        '%s::$%s is read without a lock on line %d and written back from its old value: two concurrent requests read the same value, and one change is lost.',
                        $class,
                        $property,
                        $loadLine,
                    ))
                        ->file($file)
                        ->line($writeLine)
                        ->identifier('money.checkThenAct')
                        ->tip(RuleDocs::tip('check-then-act', 'Load it with lockForUpdate() (Doctrine: LockMode::PESSIMISTIC_WRITE) inside a transaction, or add a version column.'))
                        ->build();
                }
            }
        }

        return $errors;
    }

    /**
     * @param list<array{string, int, string, int|string, int|string|bool, bool|string}> $items sorted by line
     *
     * @return list<array{string, string, int, int}> class, property, load line, write line
     */
    private function lostUpdates(array $items): array
    {
        $found = [];
        $assigned = [];
        foreach ($items as $index => $item) {
            if ($item[0] === 'assign') {
                $assigned[$item[2]] = $item;
                continue;
            }
            if ($item[0] !== 'write') {
                continue;
            }
            [, , $variable, $property, $line, $money] = $item;
            $load = $assigned[$variable] ?? null;
            if ($load === null || $load[4] === '' || $load[5] === true || !is_string($property) || !is_int($line)) {
                continue;
            }
            $class = (string) $load[4];
            if ($money !== true && !in_array(strtolower($property), $this->balances->columnsOfClass($class), true)) {
                continue;
            }
            if (!$this->persistedAfter($items, $index, $variable)) {
                continue;
            }
            $key = $class . '::' . $property . '@' . $load[3];
            $found[$key] ??= [$class, $property, (int) $load[3], $line];
        }

        return array_values($found);
    }

    /**
     * @param list<array{string, int, string, int|string, int|string|bool, bool|string}> $items
     */
    private function persistedAfter(array $items, int $from, string $variable): bool
    {
        for ($i = $from + 1, $count = count($items); $i < $count; $i++) {
            if ($items[$i][0] === 'persist' && ($items[$i][2] === $variable || $items[$i][2] === '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{string, int, string, int|string, int|string|bool, bool|string} $item
     */
    private static function line(array $item): int
    {
        return (int) ($item[0] === 'write' ? $item[4] : $item[3]);
    }
}
