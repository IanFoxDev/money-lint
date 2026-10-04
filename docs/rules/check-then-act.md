# money.checkThenAct

Money read without a lock, changed from its old value and saved.

## What fails

```php
$wallet = Wallet::find($id);          // balance = 100
$wallet->balance -= $amount;          // 100 - 30
$wallet->save();                      // UPDATE wallets SET balance = 70
```

Two requests for the same wallet run this at the same moment, for example a payment
and a refund, or the same webhook delivered twice. Both read 100. Both write 70. The
wallet should hold 40; it holds 70, and 30 has disappeared without an error or a log
line.

A transaction alone does not help: the plain `SELECT` takes no lock under the default
isolation level of PostgreSQL or MySQL, so both transactions read the same row and
both commit.

## What is reported

In one function, in this order:

1. a variable gets a row loaded **without a lock**: Eloquent `find()`, `findOrFail()`,
   `first()`, `firstOrFail()`, `firstWhere()`, `sole()` (on the model or its query) with
   no `lockForUpdate()` or `sharedLock()` in the chain; Doctrine `find()` without a lock
   mode, `findOneBy()`;
2. a money property of that variable is changed **from its old value**: `-=`, `+=`,
   `++`, or `=` with the same property on the right;
3. the change is saved: `save()`, `push()`, `update()` on the variable, or `flush()`.

A property is money when [ADR 0001](../adr/0001-what-counts-as-money.md) says so, or
when it is listed in `parameters.money.balances`.

## What is not reported

- Loads with `lockForUpdate()`, `sharedLock()`, or a Doctrine lock mode.
- Doctrine entities with a `#[Version]` property: Doctrine detects the lost update and
  throws `OptimisticLockException`.
- A property set to a value that does not depend on the old one (`$order->amount =
  $request->amount`).
- A variable loaded again, with a lock, before the change.
- Loads and saves in different functions: the rule looks at one function at a time.

## How to fix

Lock the row for the rest of the transaction:

```php
DB::transaction(function () use ($id, $amount) {
    $wallet = Wallet::lockForUpdate()->findOrFail($id);
    $wallet->balance -= $amount;
    $wallet->save();
});
```

```php
$em->wrapInTransaction(function () use ($em, $id, $amount) {
    $account = $em->find(Account::class, $id, LockMode::PESSIMISTIC_WRITE);
    $account->debit($amount);
});
```

Or add a version column (Doctrine `#[ORM\Version]`) and retry on
`OptimisticLockException`. If the balance has a ledger, post an entry through it
instead (see [money.balanceOutsideLedger](balance-outside-ledger.md)).

To silence one line, use `// @phpstan-ignore money.checkThenAct`.
