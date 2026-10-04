<?php

declare(strict_types=1);

namespace FloatConversions;

use IanFoxDev\MoneyLint\Attribute\Money;

final class Payment
{
    public int $amount = 0;

    #[Money]
    public string $total = '0';

    public int $quantity = 0;

    public static int $balance = 0;
}

function convert(Payment $payment, int $amount, int $count, ?Payment $maybe): void
{
    $a = (float) $payment->amount;
    $b = (float) $payment->total;
    $c = floatval($amount);
    $e = round($payment->amount, 2);
    $f = round($payment->amount, precision: 2);
    $payment->amount = 10.5;
    $payment->amount += 0.01;
    $g = (float) $maybe?->amount;
    Payment::$balance = -1.5;
    $h = (float) Payment::$balance;

    // Not money, or not a float.
    $ok1 = (float) $payment->quantity;
    $ok2 = (float) $count;
    $ok3 = round($payment->amount);
    $ok4 = round($payment->amount, 0);
    $ok5 = (int) $payment->amount;
    $payment->amount = 10;
    $payment->quantity = 1.5;
    $local = $payment->amount;
    $ok6 = (float) $local;
    // Only the sign or the zero is checked: exact in a float.
    $ok8 = (float) $payment->amount > 0;
    $ok9 = ! (float) $payment->amount;
    $ok10 = floatval($amount) == 0 ? 'free' : 'paid';
    if ((float) $payment->total) {
        $ok11 = 0 < (float) $payment->amount;
    }
    // Cents to a decimal number, usually for display: too often right to report.
    $ok7 = round($payment->amount / 100, 2);
}
