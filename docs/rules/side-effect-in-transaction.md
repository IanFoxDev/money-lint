# money.sideEffectInTransaction

An HTTP request, an email or a message to a broker sent from inside a database
transaction.

## What fails

```php
DB::transaction(function () use ($order) {
    $order->update(['status' => 'paid']);
    Http::post('https://psp.example/charges', ['amount' => $order->amount]);
    $order->items()->update(['reserved' => true]);
});
```

Three ways this loses money or time:

- **The transaction rolls back, the request does not.** The last update fails on a
  constraint or a deadlock. The database forgets the order was paid; the payment
  provider charged the card anyway. Nothing in your system knows about that charge
  until reconciliation finds it.
- **The request runs again.** `DB::transaction($callback, attempts: 3)` runs the whole
  callback again after a deadlock or a failed commit. The charge goes out twice.
- **The transaction stays open while the request waits.** Row locks taken by the first
  update are held for as long as the provider takes to answer, up to the client
  timeout. Every other payment for the same order or wallet waits behind them, and a
  slow provider turns into lock timeouts and an exhausted connection pool.

## What is reported

A call from `parameters.money.sideEffects` made in a callback passed to a method from
`parameters.money.transactions`, directly or through a callback PHP runs right away
(`array_map()`, `array_filter()`, `usort()` and similar).

Transactions by default: Laravel `DB::transaction()` and `ConnectionInterface::transaction()`,
Doctrine DBAL `Connection::transactional()`, Doctrine ORM
`EntityManagerInterface::wrapInTransaction()`.

Side effects by default: PSR-18 `sendRequest()`, Guzzle, Symfony HttpClient, Laravel
`Http` and `PendingRequest`, Laravel `Mail` and Symfony Mailer, php-amqplib
`basic_publish()`, php-rdkafka `produce()`, and every call on a stripe-php service
(`$stripe->paymentIntents->create()`).

Add your own in `phpstan.neon`, for example a provider SDK or a transaction helper:

```neon
parameters:
  money:
    transactions:
      - App\Database\Transactions::run
    sideEffects:
      - App\Psp\AcmeClient::*
      - App\Notifications\Sms::send|queue
```

A pattern is `Class::method`; the class matches its subtypes, several methods are
joined with `|`, `*` means any method.

## What is not reported

- Calls in a callback that may run later: `DB::afterCommit()`, a collection's
  `each()`, an event listener. The rule cannot tell when they run, so it stays silent.
- A callback passed as a variable (`DB::transaction($callback)`) or defined elsewhere.
- Transactions opened with `beginTransaction()` and closed with `commit()` by hand.
- A method named `transaction()` on something that is not a database connection.

## How to fix

Do the external call after the commit, and make the database the source of truth for
what still has to be sent:

```php
$order = DB::transaction(function () use ($order) {
    $order->update(['status' => 'charging']);
    return $order;
});

$charge = Http::post('https://psp.example/charges', [...]); // outside, with an idempotency key

DB::transaction(fn() => $order->update(['status' => 'paid', 'charge_id' => $charge['id']]));
```

For events and messages, write a row to an outbox table inside the transaction and
publish it from a separate process after commit (for example with
[ianfoxdev/outbox](https://github.com/IanFoxDev/outbox)). For notifications in Laravel,
`DB::afterCommit()` or a queued job with `afterCommit()` runs only once the data is
there.

To silence one call, use `// @phpstan-ignore money.sideEffectInTransaction`.
