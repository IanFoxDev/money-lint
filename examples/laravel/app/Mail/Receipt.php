<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;

final class Receipt extends Mailable
{
    public function __construct(public readonly Order $order) {}
}
