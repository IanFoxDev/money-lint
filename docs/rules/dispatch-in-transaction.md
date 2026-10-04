# money.dispatchInTransaction

A Laravel job or mail put on the queue inside a database transaction, without waiting
for the commit.

## What fails

```php
DB::transaction(function () use ($payment) {
    $payment->update(['status' => 'captured']);
    CreditWallet::dispatch($payment->id);
    $payment->order->update(['status' => 'paid']);
});
```

The job is on the queue as soon as `dispatch()` runs, while the transaction is still
open. Two things can happen next:

- **A worker is faster than the commit.** It loads the payment, sees the old status (or
  no row at all), and skips the credit or fails with `ModelNotFoundException`. After
  retries the job lands in `failed_jobs`, and the customer paid for nothing.
- **The transaction rolls back.** The last update hits a deadlock or a constraint. The
  payment was never captured, but the job is already queued and credits the wallet.

Tests rarely catch this. With the `sync` queue driver the job runs in the same process
and the same connection, so it sees the uncommitted rows and passes.

## What is reported

In a callback of `DB::transaction()` and the other transaction methods (see
[money.sideEffectInTransaction](side-effect-in-transaction.md)):

- `dispatch($job)` and `SomeJob::dispatch()` for a job that implements `ShouldQueue`;
- `dispatch(function () {...})`: a queued closure;
- `Bus::dispatch()` and `Dispatcher::dispatch()` for a job that implements `ShouldQueue`;
- `Mail::queue()`, `Mail::later()`, `Mail::to(...)->queue()`;
- `Queue::push()` and `Queue::later()`.

## What is not reported

- `->afterCommit()` anywhere in the chain: `dispatch($job)->afterCommit()`,
  `SomeJob::dispatch()->onQueue('x')->afterCommit()`, `dispatch($job->afterCommit())`.
- `->beforeCommit()`: an explicit choice.
- Jobs that implement `ShouldQueueAfterCommit`, declare `$afterCommit = true`, or call
  `$this->afterCommit()` in the constructor.
- Jobs that do not implement `ShouldQueue`: they run at once, in the transaction.
- Everything, when `money.queueAfterCommit` is `true` (see below).

## How to fix

Pick one, in order of preference:

1. Make the queue wait for commits everywhere: `'after_commit' => true` for each
   connection in `config/queue.php`. Then tell the linter:

   ```neon
   parameters:
     money:
       queueAfterCommit: true
   ```

2. Mark the job: `final class CreditWallet implements ShouldQueueAfterCommit`.

3. Mark the call: `CreditWallet::dispatch($payment->id)->afterCommit();`

With any of them Laravel holds the job until the transaction commits and drops it if
the transaction rolls back.

To silence one call, use `// @phpstan-ignore money.dispatchInTransaction`.
