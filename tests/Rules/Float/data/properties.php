<?php

declare(strict_types=1);

namespace FloatProperties;

use IanFoxDev\MoneyLint\Attribute\Money;

final class Order
{
    public float $amount = 0.0;

    public ?float $refundAmount = null;

    public int|float $balance = 0;

    #[Money]
    public float $total = 0.0;

    /** @var float */
    public $discountAmount;

    public function __construct(
        public float $shippingAmount,
        #[Money] private float $tip,
    ) {}
}

final class NotMoney
{
    // Money, but not as a float.
    public int $amount = 0;

    public string $balance = '0.00';

    // Floats, but not money.
    public float $weight = 0.0;

    public float $amountInStock = 0.0;

    /** @var float|string */
    public $discountAmount;

    public function __construct(public float $rate) {}
}
