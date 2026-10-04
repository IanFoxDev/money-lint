<?php

declare(strict_types=1);

namespace IanFoxDev\MoneyLint\Attribute;

use Attribute;

/**
 * Marks a property or parameter as an amount of money, so the money rules check it.
 *
 * PHP loads an attribute class only when the attribute is instantiated through
 * reflection, so code marked with it runs without this package installed.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final class Money {}
