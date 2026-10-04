# Security

money-lint runs inside PHPStan on your machine or in CI. It reads your code and
reports; it does not execute it, does not make network requests, and does not change
files.

If you find a vulnerability, for example a way for analysed code to make the
extension run something, do not open a public issue. Report it privately through
[GitHub](https://github.com/IanFoxDev/money-lint/security/advisories/new), or write to
ianfoxdeveloper@gmail.com.

A missed bug or a false report is not a security issue: open a normal issue for it.

## Supported versions

Fixes go into the latest release only. Until 1.0 that is the latest `0.x` tag.
