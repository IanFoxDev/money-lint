<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    // Rule fixtures keep the code the rules report, formatting included.
    ->exclude('data');

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS2.0' => true,
        'declare_strict_types' => true,
        'no_unused_imports' => true,
        'ordered_imports' => true,
    ])
    ->setFinder($finder);
