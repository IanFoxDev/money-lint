<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Ledger\Ledger;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

final class CreditWallet implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct(public readonly int $orderId) {}

    public function handle(Ledger $ledger): void
    {
        $order = Order::findOrFail($this->orderId);
        $ledger->post($order->wallet_id, $order->amount, 'order ' . $order->id);
    }
}
