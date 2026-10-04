# Precision on real code

A linter is worth running only if most of what it reports is right. This page shows
how the rules did on open-source applications that handle money, and what changed
because of it.

## The run

- Five open-source PHP applications: invoicing, accounting, e-commerce (two) and
  personal finance. Four on Laravel, one on Symfony with Doctrine. About 12,000 PHP
  files in the analysed paths, tests excluded.
- money-lint as of this page, PHPStan 2.2, only the money rules
  (`customRulesetUsed: true`), no Larastan.
- The configuration the README recommends and nothing project-specific:

  ```neon
  parameters:
    money:
      properties: ['*amount', '*balance', '*fee']
  ```

  `money.balanceOutsideLedger` needs a list of balances and the ledger classes, which
  only the team that owns the code can write. It was not measured.

- Every report from four of the applications was labelled by hand. The fifth, which
  stores most amounts as floats on purpose, had 280 reports; a random sample of 40 was
  labelled.

The project names and the individual reports are left out until the maintainers have
seen the ones that matter.

## Results

| Rule | Reports | Labelled | Wrong | Notes |
|---|---|---|---|---|
| `money.float` | 308 | 68 | 4 | see below |
| `money.dispatchInTransaction` | 2 | 2 | 0 | both real: a log job and a "payment failed" email queued before the commit, with `after_commit` off |
| `money.sideEffectInTransaction` | 0 | | | |
| `money.checkThenAct` | 0 | | | |

What the 68 labelled `money.float` reports point at:

| Label | Count | Example |
|---|---|---|
| Money in a float where it changes or decides something | 17 (25%) | applying a payment to an invoice, the amount still refundable, a gateway fee, a client balance update |
| Money in a float at a boundary: a provider API, an e-invoice, an export | 26 (38%) | a DTO for a tax subtotal, an amount formatted for a payment provider, often rounded right there |
| Money in a float that does not matter: display, charts, reports, API output | 21 (31%) | `(float) $invoice->amount` in a JSON transformer |
| Not money | 4 (6%) | a tax rate stored in a property called `$amount` |

So the rule finds money in floats with 94% precision, and one report in four is in
code where that can cost a cent. On a codebase that uses floats on purpose, start
with PHPStan's baseline (`--generate-baseline`) and look at new code only.

## What the run changed

The first run had 340 reports. Two fixes came out of reading them:

- **Six false reports of `money.sideEffectInTransaction`** on an Eloquent model saved
  in a transaction. PHPStan typed the model as `never` (it could not infer a query
  result without Larastan), and `never` is a subtype of every class, so it matched the
  stripe-php pattern `Stripe\Service\AbstractService::*`. Types without a known class
  no longer match any pattern.
- **24 `money.float` reports on zero checks**: `(float) $amount > 0`,
  `! (float) $discount`, `floatval($balance) == 0`. A float keeps the sign and the zero
  of a decimal amount exactly, so these are no longer reported.

The four reports that are not money come from a name pattern meeting a field that only
looks like an amount. Narrow the pattern, or mark money with `#[Money]` instead.
