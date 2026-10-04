<?php

declare(strict_types=1);

namespace FloatParameters;

use IanFoxDev\MoneyLint\Attribute\Money;

function charge(float $amount, ?float $balance, #[Money] float $total, float $rate, int $refundAmount): void {}

$refund = static fn(float $amount): float => $amount;

final class Wallet
{
    public function credit(int|float $amount, string $currency): void {}
}
