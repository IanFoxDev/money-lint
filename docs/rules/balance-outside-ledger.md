# money.balanceOutsideLedger

A balance changed anywhere except the ledger.

## What fails

A balance column is a cache of the ledger: the sum of the entries posted to an
account. When some code changes it without posting an entry, the two disagree, and
nothing records why:

```php
// A support tool, written in a hurry
$wallet->increment('balance', 500);

// A refund path that "just fixes the number"
DB::update('UPDATE wallets SET balance = balance - ? WHERE id = ?', [$amount, $id]);
```

Reconciliation finds a wallet whose balance is 500 more than its entries add up to.
There is no entry, no author, no reason, and often no way to tell which of a hundred
call sites did it. With the read-modify-write form (`$wallet->balance -= $amount;
$wallet->save();`) two concurrent requests also lose one of the updates.

## What is reported

Nothing, until you list the balances and who may change them:

```neon
parameters:
  money:
    balances:
      - App\Models\Wallet::balance   # an Eloquent model or a Doctrine entity
      - wallets.balance              # a table, for the query builder and SQL
    balanceWriters:
      - App\Ledger\Ledger            # the ledger and the classes it delegates to
```

Then, outside the classes in `balanceWriters`:

- assignment to the property: `$wallet->balance = ...`, `+=`, `-=`, `++`;
- Eloquent `increment()`, `decrement()`, `incrementEach()` and their `Quietly`
  forms, `update()`, `fill()` and `forceFill()` with the column, on a model or on its
  query builder (`Wallet::query()->where(...)->increment('balance')`);
- the setter `setBalance()` on an entity;
- the query builder on the table: `DB::table('wallets')->increment('balance')`,
  `->update(['balance' => ...])`;
- SQL and DQL strings: `UPDATE wallets SET balance = ...`,
  `UPDATE App\Entity\Wallet w SET w.balance = ...`.

Assignments in the constructor of the listed class itself are not reported, so an
entity can start with a zero balance.

## What is not reported

- Columns and tables that are not listed; reading the balance.
- Column names that are not literals (`increment($column)`).
- SQL built from pieces, unless one literal piece holds both `UPDATE <table>` and the
  `SET` with the column.
- `Wallet::create(['balance' => 0])`: creating a row is not changing a balance.

## How to fix

Route the change through the ledger, which posts an entry and updates the balance in
the same transaction:

```php
$ledger->post(new Entry(
    account: $wallet->id,
    amount: 500,
    reason: 'goodwill credit, ticket 4512',
));
```

If an entity changes its own balance in methods like `credit()` and `debit()`, and
those methods are the ledger, list the entity in `balanceWriters`.

To silence one line, use `// @phpstan-ignore money.balanceOutsideLedger`.
