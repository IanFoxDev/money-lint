# money.float

Money held in a `float`.

## What fails

A float stores binary fractions. Most decimal amounts have no exact binary form, so
`19.99` is stored as a number a little below or above it. The error is invisible until
the value is converted, summed or compared:

```php
var_dump((int) (19.99 * 100)); // int(1998): one cent lost on the way to minor units
var_dump((int) (0.29 * 100));  // int(28)

$total = 0.0;
for ($i = 0; $i < 10; $i++) {
    $total += 0.1;
}
var_dump($total == 1.0);       // bool(false)
```

In a payment flow this shows up as an order that is one cent short of the captured
amount, or a balance that does not match the sum of its entries by a fraction that
grows with every operation.

## What is reported

For values the configuration marks as money (see
[ADR 0001](../adr/0001-what-counts-as-money.md)):

- a property or promoted constructor parameter with a native type that includes
  `float` (`float`, `?float`, `int|float`), or with no native type and `@var float`;
- a parameter of a function, method or closure typed with `float`;
- `(float) $money` and `floatval($money)`;
- `round($money, 2)`: rounding to decimal places means the value is a float in major
  units;
- a float literal assigned to a money property: `$order->amount = 10.5`, `+= 0.01`.

A money value is a property marked as money, or a parameter of the current function
marked as money. Local variables are not followed.

## What is not reported

- `round($cents / 100, 2)` and other arithmetic on money: it is usually formatting
  for display, and reporting it would be wrong too often.
- `round($money)` without decimal places.
- Floats that are not marked as money: rates, weights, percentages.
- Values of `Money\Money` or `Brick\Money\Money`.

## How to fix

Pick one representation and keep it from the database to the API:

- integers in minor units (`1999` for 19.99 EUR), with the currency next to them;
- decimal strings (`'19.99'`) with `bcmath` or `brick/math` for arithmetic;
- a money object: `moneyphp/money` or `brick/money`.

```php
// Before
public float $amount;

// After
public int $amountMinor;   // 1999
public string $currency;   // 'EUR'
```

If a float is truly intended, for example an exchange rate stored with an amount,
rename it or leave it unmarked. To silence one line, use
`// @phpstan-ignore money.float`.
