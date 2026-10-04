# 0001. What counts as money, and when a rule stays silent

Date: 2026-10-04. Status: accepted.

## Context

Every rule in this package starts with the same question: is this value an amount of
money? A float that holds a temperature is fine. A float that holds a balance loses a
cent every few thousand operations. `$amount` can be money in a payment service and a
quantity of items in a warehouse.

PHPStan sees types, not meaning. `int $amount` and `int $quantity` look the same. So the
package needs a way to be told which values are money, and a rule for what to do when
nobody told it.

The second question is how sure a rule must be before it reports. A static rule that
fires on correct code gets disabled in `ignoreErrors` on the first day, and then it
catches nothing. Teams adopt a linter on an old codebase, where the first run decides
whether it stays.

## Decision

A value is money when one of these says so:

1. **An attribute** on the property or parameter: `#[IanFoxDev\MoneyLint\Attribute\Money]`,
   or any attribute class listed in `parameters.money.attributes` (a project may
   already have its own).
2. **A name pattern** from `parameters.money.properties`, matched case-insensitively
   against property and parameter names: `['*amount', '*balance', 'fee']`. Patterns use
   `fnmatch()` syntax.
3. **A money type** from a library (`Money\Money`, `Brick\Money\Money`). Values of
   these types are already safe from float and rounding mistakes, so the arithmetic
   rules skip them. The transaction and locking rules still apply to the entities
   that hold them.

Name patterns are **empty by default**. Without configuration, only the attribute
marks money. This makes the first run on an old codebase quiet, and each team decides
how wide to go. The README shows a common set of patterns to start from.

The attribute class ships in this package, which is installed with `--dev`. PHP does
not load an attribute class unless `ReflectionAttribute::newInstance()` is called, so
production code with `#[Money]` runs without the package. Code that reads attributes
at runtime should use a project-level attribute and list it in
`parameters.money.attributes`.

A rule reports only when the failure is certain from the code it sees. When the rule
cannot tell (a value passes through `mixed`, a transaction is opened in another
method, a lock may be taken by a caller) it stays silent. Every rule has test fixtures
for both sides: code it must report and similar code it must not.

Every error has an identifier (`money.float`, `money.sideEffectInTransaction`, ...)
for a targeted `@phpstan-ignore`, one sentence about what fails, and a link to a page
in `docs/rules/` that shows the failure and the fix.

## Consequences

- Out of the box the package reports little. That is deliberate; the README says so
  and starts with configuration.
- Recall is lower than a rule that guesses. A missed bug costs the same as before the
  linter; a false error costs the linter.
- Framework knowledge (Laravel's `DB::transaction()`, Doctrine's `wrapInTransaction()`,
  Eloquent's `lockForUpdate()`) lives in the rules as class and method names. The
  package does not require Laravel or Doctrine.
- Precision is measured, not assumed: the rules run against open-source applications
  that handle money, every report is labelled by hand, and the numbers go into
  `docs/precision.md`.
