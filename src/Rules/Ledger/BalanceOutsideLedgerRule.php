<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Rules\Ledger;

use IanFoxDev\MoneyLint\Rules\RuleDocs;
use IanFoxDev\MoneyLint\Types;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * A balance listed in money.balances changed outside the classes listed in
 * money.balanceWriters.
 *
 * @implements Rule<Expr>
 */
final class BalanceOutsideLedgerRule implements Rule
{
    private const ELOQUENT_BUILDER = 'Illuminate\Database\Eloquent\Builder';
    private const QUERY_BUILDER = 'Illuminate\Database\Query\Builder';

    /** Methods that change one column given as the first argument; the third is $extra. */
    private const ONE_COLUMN = ['increment', 'decrement', 'incrementquietly', 'decrementquietly'];

    /** Methods that change the columns given as keys of the first argument. */
    private const ARRAY_KEYS = [
        'incrementeach', 'decrementeach', 'incrementeachquietly', 'decrementeachquietly',
        'update', 'updatequietly', 'fill', 'forcefill', 'updateorfail',
    ];

    public function __construct(private readonly Balances $balances) {}

    public function getNodeType(): string
    {
        return Expr::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->balances->isEmpty() || $this->balances->isWriter($scope)) {
            return [];
        }

        if ($node instanceof Expr\Assign || $node instanceof Expr\AssignOp || $node instanceof Expr\AssignRef
            || $node instanceof Expr\PreInc || $node instanceof Expr\PostInc
            || $node instanceof Expr\PreDec || $node instanceof Expr\PostDec) {
            return $this->assignment($node->var, $scope);
        }

        if (($node instanceof Expr\MethodCall || $node instanceof Expr\NullsafeMethodCall) && $node->name instanceof Identifier) {
            return $this->methodCall($node, $node->name->toString(), $scope);
        }

        if ($node instanceof String_) {
            return $this->sql($node->value);
        }

        return [];
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function assignment(Expr $var, Scope $scope): array
    {
        if (!($var instanceof Expr\PropertyFetch || $var instanceof Expr\NullsafePropertyFetch) || !$var->name instanceof Identifier) {
            return [];
        }
        $listed = $this->balances->forType($scope->getType($var->var));
        if ($listed === null) {
            return [];
        }
        [$class, $columns] = $listed;
        $column = $var->name->toString();
        if (!in_array(strtolower($column), $columns, true)) {
            return [];
        }
        // An entity setting its own starting balance.
        if ($scope->getFunctionName() === '__construct' && $scope->getClassReflection()?->is($class) === true) {
            return [];
        }

        return [$this->error($class . '::$' . $column, 'an assignment')];
    }

    /**
     * @param Expr\MethodCall|Expr\NullsafeMethodCall $call
     *
     * @return list<IdentifierRuleError>
     */
    private function methodCall(Expr $call, string $method, Scope $scope): array
    {
        $lower = strtolower($method);
        $receiver = $scope->getType($call->var);

        // $wallet->setBalance(), $wallet->increment('balance'), Wallet::query()->increment('balance').
        $model = $this->balances->forType($receiver);
        if ($model === null && Types::isInstanceOf($receiver, self::ELOQUENT_BUILDER)) {
            $model = $this->balances->forType($receiver->getTemplateType(self::ELOQUENT_BUILDER, 'TModel'));
        }
        if ($model !== null) {
            [$class, $columns] = $model;
            foreach ($columns as $column) {
                if ($lower === 'set' . str_replace('_', '', $column)) {
                    return [$this->error($class . '::$' . $column, $method . '()')];
                }
            }
            foreach ($this->changedColumns($call, $lower, $scope) as $column) {
                if (in_array(strtolower($column), $columns, true)) {
                    return [$this->error($class . '::$' . $column, $method . '()')];
                }
            }

            return [];
        }

        // DB::table('wallets')->...->increment('balance').
        if (Types::isInstanceOf($receiver, self::QUERY_BUILDER)) {
            $table = $this->tableOf($call->var, $scope);
            if ($table === null) {
                return [];
            }
            $columns = $this->balances->columnsOfTable($table);
            foreach ($this->changedColumns($call, $lower, $scope) as $column) {
                if (in_array(strtolower($column), $columns, true)) {
                    return [$this->error($table . '.' . $column, $method . '()')];
                }
            }
        }

        return [];
    }

    /**
     * Column names a call changes, when they are written as literals.
     *
     * @param Expr\MethodCall|Expr\NullsafeMethodCall $call
     *
     * @return list<string>
     */
    private function changedColumns(Expr $call, string $method, Scope $scope): array
    {
        $args = $call->getArgs();
        $columns = [];
        if (in_array($method, self::ONE_COLUMN, true)) {
            if (isset($args[0])) {
                foreach ($scope->getType($args[0]->value)->getConstantStrings() as $string) {
                    $columns[] = $string->getValue();
                }
            }
            if (isset($args[2])) {
                $columns = [...$columns, ...$this->arrayKeys($args[2]->value, $scope)];
            }
        } elseif (in_array($method, self::ARRAY_KEYS, true) && isset($args[0])) {
            $columns = $this->arrayKeys($args[0]->value, $scope);
        }

        return $columns;
    }

    /**
     * @return list<string>
     */
    private function arrayKeys(Expr $expr, Scope $scope): array
    {
        $keys = [];
        foreach ($scope->getType($expr)->getConstantArrays() as $array) {
            foreach ($array->getKeyTypes() as $key) {
                foreach ($key->getConstantStrings() as $string) {
                    $keys[] = $string->getValue();
                }
            }
        }

        return $keys;
    }

    /**
     * The table of a query builder chain that starts with table('name').
     */
    private function tableOf(Expr $expr, Scope $scope): ?string
    {
        while ($expr instanceof Expr\MethodCall || $expr instanceof Expr\NullsafeMethodCall || $expr instanceof Expr\StaticCall) {
            if ($expr->name instanceof Identifier && $expr->name->toLowerString() === 'table') {
                $args = $expr->getArgs();
                $strings = isset($args[0]) ? $scope->getType($args[0]->value)->getConstantStrings() : [];

                return count($strings) === 1 ? $strings[0]->getValue() : null;
            }
            if ($expr instanceof Expr\StaticCall) {
                return null;
            }
            $expr = $expr->var;
        }

        return null;
    }

    /**
     * UPDATE wallets SET balance = ... in SQL, UPDATE App\Entity\Wallet w SET w.balance = ... in DQL.
     *
     * @return list<IdentifierRuleError>
     */
    private function sql(string $string): array
    {
        if (preg_match('/\bupdate\s+[`"\[]?([\w.\\\\]+)[`"\]]?\s+(?:as\s+)?(?:(?!set\b)\w+\s+)?set\s+(.+)/is', $string, $match) !== 1) {
            return [];
        }
        [, $target, $set] = $match;
        if (str_contains($target, '\\')) {
            $columns = $this->balances->columnsOfClass($target);
            $label = static fn(string $column): string => ltrim($target, '\\') . '::$' . $column;
            $how = 'a DQL UPDATE';
        } else {
            $columns = $this->balances->columnsOfTable($target);
            $label = static fn(string $column): string => $target . '.' . $column;
            $how = 'an SQL UPDATE';
        }
        foreach ($columns as $column) {
            if (preg_match('/(?:^|[\s,(.`"])' . preg_quote($column, '/') . '[`"\]]?\s*=/i', $set) === 1) {
                return [$this->error($label($column), $how)];
            }
        }

        return [];
    }

    private function error(string $balance, string $how): IdentifierRuleError
    {
        return RuleErrorBuilder::message(sprintf(
            'Balance %s is changed outside the ledger by %s. Only %s may change it.',
            $balance,
            $how,
            $this->balances->describeWriters(),
        ))
            ->identifier('money.balanceOutsideLedger')
            ->tip(RuleDocs::tip('balance-outside-ledger', 'Post an entry through the ledger and let it update the balance in the same transaction.'))
            ->build();
    }
}
