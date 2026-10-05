# money-lint

[![php](https://github.com/IanFoxDev/money-lint/actions/workflows/php.yml/badge.svg)](https://github.com/IanFoxDev/money-lint/actions/workflows/php.yml)
[![examples](https://github.com/IanFoxDev/money-lint/actions/workflows/examples.yml/badge.svg)](https://github.com/IanFoxDev/money-lint/actions/workflows/examples.yml)

PHPStan rules for code that moves money.

The bugs that cost money in payment and billing code are rarely clever. They are the
same few mistakes in every codebase: a call to the payment provider inside a database
transaction, a job queued before the commit, a balance read without a lock and written
back, a balance changed without a ledger entry, an amount in a float. A careful
reviewer catches them. A linter catches them every time.

> Status: v0.1. Until 1.0 a minor version may change rule identifiers or settings;
> such changes are marked **BREAKING** in the [CHANGELOG](CHANGELOG.md).

## Install

```bash
composer require --dev ianfoxdev/money-lint
```

With [phpstan/extension-installer](https://github.com/phpstan/extension-installer) the
rules are on. Without it, add one line to `phpstan.neon`:

```neon
includes:
  - vendor/ianfoxdev/money-lint/extension.neon
```

PHPStan 2.1.3 or later, PHP 8.3 or later. Laravel and Doctrine are recognised by class
names; the package does not depend on either. Works next to Larastan and
phpstan-doctrine.

## What it reports

```php
DB::transaction(function () use ($order) {
    $order->update(['status' => 'paid']);
    Http::post('https://psp.example/charges', ['amount' => $order->amount]);
    CreditWallet::dispatch($order->id);
});
```

```
CheckoutController.php:25:Http::post() runs inside DB::transaction(): it cannot be rolled back with the transaction, and the transaction stays open while it waits. [identifier=money.sideEffectInTransaction]
CheckoutController.php:26:The job CreditWallet is queued inside DB::transaction(): a worker can run it before the commit and miss the data, or after a rollback. [identifier=money.dispatchInTransaction]
```

Each error links to a page that shows how the bug loses money and how to fix it.

| Identifier | Reports | On by default |
|---|---|---|
| [`money.sideEffectInTransaction`](docs/rules/side-effect-in-transaction.md) | An HTTP call, email or broker message inside `DB::transaction()`, `wrapInTransaction()` or `transactional()` | yes |
| [`money.dispatchInTransaction`](docs/rules/dispatch-in-transaction.md) | A Laravel job, queued closure or mail queued inside a transaction without `afterCommit()` | yes |
| [`money.checkThenAct`](docs/rules/check-then-act.md) | Money read without a lock, changed from its old value and saved: a lost update under concurrency | once money is marked |
| [`money.float`](docs/rules/float.md) | Money held in a float: typed `float`, cast with `(float)`, rounded to decimal places | once money is marked |
| [`money.balanceOutsideLedger`](docs/rules/balance-outside-ledger.md) | A balance you listed changed outside the ledger: `increment()`, `+=`, `UPDATE ... SET balance` | once balances are listed |

## Tell it what money is

PHPStan sees `int $amount` and `int $quantity` as the same type. The money rules check
only values you mark, so nothing is marked by default and the first run on an old
codebase is quiet. Why: [docs/adr/0001-what-counts-as-money.md](docs/adr/0001-what-counts-as-money.md).

```neon
parameters:
  money:
    # property and parameter names, fnmatch() patterns, case-insensitive
    properties: ['*amount', '*balance', '*fee']

    # balances only the ledger may change, and the ledger
    balances:
      - App\Models\Wallet::balance
      - wallets.balance
    balanceWriters:
      - App\Ledger\Ledger

    # set when every queue connection has 'after_commit' => true
    queueAfterCommit: false
```

Or mark a property or parameter with `#[IanFoxDev\MoneyLint\Attribute\Money]`. PHP does
not load an attribute class unless asked, so production code runs without the package.

Transactions and side effects are lists too. Add your payment provider's SDK or your
own transaction helper:

```neon
parameters:
  money:
    transactions:
      - App\Database\Transactions::run
    sideEffects:
      - App\Psp\AcmeClient::*
```

On a large codebase, start from a baseline (`vendor/bin/phpstan analyse --generate-baseline`)
and look at new code first. One line is silenced with
`// @phpstan-ignore money.float` and the like.

## How often it is right

On five open-source applications that handle money, `money.float` found money in
floats with 94% precision, and one report in four was in code where it can cost a cent.
Both transaction reports were real. The run and what it changed:
[docs/precision.md](docs/precision.md).

The rules prefer silence to guessing. A transaction opened in another method, a
callback that may run later, a value PHPStan cannot type: none of these are reported.

## Examples

[examples/](examples) has a Laravel checkout and Symfony refunds with each bug in one
file and the same code fixed in another. CI checks that every bug is reported and the
fixed code is clean, with Larastan and phpstan-doctrine loaded next to money-lint.

## Documentation

- Rules: [float](docs/rules/float.md), [side effect in transaction](docs/rules/side-effect-in-transaction.md),
  [dispatch in transaction](docs/rules/dispatch-in-transaction.md),
  [balance outside ledger](docs/rules/balance-outside-ledger.md),
  [check then act](docs/rules/check-then-act.md)
- [Precision on real code](docs/precision.md)
- [Decision records](docs/adr)

## Contributing

A false report is the most useful bug report: open an issue with the "False report"
template and the smallest code that shows it. See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

[MIT](LICENSE)
