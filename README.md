# money-lint

PHPStan rules for code that moves money.

The most expensive bugs in payment and billing code are mechanical, and they repeat
from project to project: a float in a balance, an HTTP call to the payment provider
inside a database transaction, `balance = balance + ?` that skips the ledger, a row
read without a lock and written back. A careful reviewer catches them. A linter catches
them every time.

> Status: in development, nothing released yet.

## How it decides what is money

PHPStan sees `int $amount` and `int $quantity` as the same type. The rules check only
values you mark: with the `#[Money]` attribute, or with name patterns in
`phpstan.neon`. Nothing is marked by default, so the first run on an old codebase is
quiet. Why: [docs/adr/0001-what-counts-as-money.md](docs/adr/0001-what-counts-as-money.md).

```neon
parameters:
  money:
    properties: ['*amount', '*balance', '*fee']
```

## Rules

| Identifier | Reports |
|---|---|
| [`money.float`](docs/rules/float.md) | Money held in a float: typed `float`, cast with `(float)`, rounded to decimal places |
| [`money.sideEffectInTransaction`](docs/rules/side-effect-in-transaction.md) | An HTTP call, email or broker message inside `DB::transaction()` or `wrapInTransaction()` |

## License

[MIT](LICENSE)
