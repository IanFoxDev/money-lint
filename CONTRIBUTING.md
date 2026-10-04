# Contributing

## Running locally

You need PHP 8.3 or later and Composer.

```bash
composer install
make test       # PHPUnit: rule tests on the fixtures in tests/Rules/*/data
make stan       # PHPStan at level max on src and tests
make cs         # PHP-CS-Fixer, PER-CS 2.0; make cs-fix rewrites files
make check      # all three
make examples   # PHPStan on examples/laravel and examples/symfony, compared with expected-errors.txt
```

The rule tests load Laravel, Doctrine, Guzzle, Symfony Mailer and stripe-php from the
dev dependencies, so PHPStan sees the real classes. They need about 300 MB of memory;
`phpunit.xml.dist` raises the limit.

## Where things are

| Path | What |
|---|---|
| `extension.neon` | Parameters, their defaults and the services. What a project includes |
| `src/MoneyDetector.php`, `src/MoneyExpressions.php` | What counts as money (ADR 0001) |
| `src/Rules/Float` | `money.float` |
| `src/Rules/Transaction` | `money.sideEffectInTransaction`, `money.dispatchInTransaction`, and the parser visitor that marks transaction callbacks |
| `src/Rules/Ledger` | `money.balanceOutsideLedger` |
| `src/Rules/Race` | `money.checkThenAct` |
| `tests/Rules/*/data` | Fixtures: code each rule must report and similar code it must not |
| `docs/rules` | One page per rule: the failure, what is reported, what is not, the fix |

## Reporting a false report

Open an issue with the "False report" template. Include the smallest code that shows
it, the configuration (`parameters.money`) and the PHPStan and money-lint versions. A
fix starts with that code added to the fixtures on the "must not report" side.

## Changing a rule

- Precision first. When a rule cannot tell from the code it sees, it stays silent
  (see [ADR 0001](docs/adr/0001-what-counts-as-money.md)). A change that finds more
  bugs and also more false reports needs a strong case.
- Every change to what a rule reports comes with fixtures for both sides.
- Update the rule's page in `docs/rules` and `CHANGELOG.md` under `[Unreleased]`.
- If the change could move the numbers in `docs/precision.md`, say so in the pull
  request.

## Pull requests

- One logical change per pull request.
- Commit messages follow [Conventional Commits](https://www.conventionalcommits.org/)
  (`feat:`, `fix:`, `docs:`, `ci:`).
- `make check` and `make examples` pass.
