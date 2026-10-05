# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/). Before 1.0, minor versions may break the
configuration or rule identifiers; such changes are marked **BREAKING**.

## [Unreleased]

## [0.1.0] - 2026-10-05

The first release: five PHPStan rules for code that moves money, for Laravel and
Doctrine. Checked on two example applications in CI and on five open-source
applications that handle money (see [docs/precision.md](docs/precision.md)).

### Added

- `#[Money]` attribute and `parameters.money` settings (`properties` name patterns,
  `attributes`) that tell the rules which values are money.
- `money.float`: money properties and parameters typed `float`; `(float)`,
  `floatval()` and `round($money, 2)` on money; float literals written into money
  properties. See [docs/rules/float.md](docs/rules/float.md).
- `money.sideEffectInTransaction`: HTTP requests, emails and broker messages sent from
  a callback of `DB::transaction()`, `wrapInTransaction()` or `transactional()`.
  Both lists can be extended in `parameters.money.transactions` and
  `parameters.money.sideEffects`. See
  [docs/rules/side-effect-in-transaction.md](docs/rules/side-effect-in-transaction.md).
- `money.dispatchInTransaction`: Laravel jobs, queued closures and mails put on the
  queue inside a transaction without `afterCommit()`. `parameters.money.queueAfterCommit`
  turns it off for apps whose queues wait for commits. See
  [docs/rules/dispatch-in-transaction.md](docs/rules/dispatch-in-transaction.md).
- `money.balanceOutsideLedger`: balances listed in `parameters.money.balances` changed
  outside the classes in `parameters.money.balanceWriters`, through assignments,
  Eloquent `increment()` and `update()`, the query builder, setters, SQL or DQL. See
  [docs/rules/balance-outside-ledger.md](docs/rules/balance-outside-ledger.md).
- `money.checkThenAct`: money loaded without a lock (Eloquent or Doctrine), changed
  from its old value and saved in the same function. See
  [docs/rules/check-then-act.md](docs/rules/check-then-act.md).

[Unreleased]: https://github.com/IanFoxDev/money-lint/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/IanFoxDev/money-lint/releases/tag/v0.1.0
