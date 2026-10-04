<?php

declare(strict_types=1);

// Runs PHPStan in an example and compares what it reports with expected-errors.txt.
// Usage, from an example directory: php ../check.php

$output = shell_exec('vendor/bin/phpstan analyse --no-progress --error-format=json --memory-limit=1G 2>/dev/null');
$report = is_string($output) ? json_decode($output, true) : null;
if (!is_array($report) || !isset($report['files']) || !is_array($report['files'])) {
    fwrite(STDERR, "PHPStan did not produce a JSON report:\n" . (is_string($output) ? $output : '') . "\n");
    exit(1);
}

$actual = [];
foreach ($report['files'] as $file => $result) {
    $path = str_replace(getcwd() . '/', '', (string) $file);
    foreach ($result['messages'] as $message) {
        $actual[] = sprintf('%s:%d %s', $path, $message['line'], $message['identifier'] ?? '-');
    }
}
foreach ($report['errors'] ?? [] as $error) {
    $actual[] = 'error: ' . $error;
}
sort($actual);

$expected = array_values(array_filter(
    array_map('trim', file('expected-errors.txt') ?: []),
    static fn(string $line): bool => $line !== '' && !str_starts_with($line, '#'),
));
sort($expected);

$missing = array_diff($expected, $actual);
$unexpected = array_diff($actual, $expected);
foreach ($missing as $line) {
    echo "missing:    $line\n";
}
foreach ($unexpected as $line) {
    echo "unexpected: $line\n";
}
if ($missing !== [] || $unexpected !== []) {
    exit(1);
}
printf("%d errors, as expected.\n", count($actual));
