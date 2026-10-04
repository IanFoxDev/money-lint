# Examples

Two small codebases with the bugs money-lint exists for, and the same code fixed next
to them. CI runs PHPStan on each and checks that it reports exactly the bugs listed in
`expected-errors.txt`: every one of them, and nothing in the fixed code.

| Example | Buggy | Fixed | Also checks |
|---|---|---|---|
| [laravel](laravel) | `CheckoutController`: PSP call and queued job inside `DB::transaction()`, a balance changed without the ledger and without a lock, a float amount | `SafeCheckoutController` | Larastan |
| [symfony](symfony) | `RefundService`: HTTP and email inside `wrapInTransaction()`, a lost update on a refund, a DQL `UPDATE` of a balance, a float amount | `SafeRefundService` | phpstan-doctrine |

Both load money-lint through `phpstan/extension-installer`, as a project would.

```bash
cd laravel        # or symfony
composer install
vendor/bin/phpstan analyse   # the errors, with explanations
php ../check.php             # the comparison CI runs
```
