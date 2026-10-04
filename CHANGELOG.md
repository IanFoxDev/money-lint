# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/). Before 1.0, minor versions may break the
configuration or rule identifiers; such changes are marked **BREAKING**.

## [Unreleased]

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
