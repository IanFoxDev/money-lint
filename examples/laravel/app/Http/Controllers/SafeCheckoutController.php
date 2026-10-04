<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CreditWallet;
use App\Ledger\Ledger;
use App\Mail\PaidReceipt;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * CheckoutController with the bugs fixed. money-lint reports nothing here.
 */
final class SafeCheckoutController
{
    public function __construct(private readonly Ledger $ledger) {}

    public function pay(Order $order): void
    {
        // The provider is called outside the transaction, with an idempotency key.
        $charge = Http::withHeaders(['Idempotency-Key' => 'order-' . $order->id])
            ->post('https://psp.example/charges', ['amount' => $order->amount]);

        DB::transaction(function () use ($order, $charge): void {
            $order->update(['status' => 'paid', 'charge_id' => $charge->json('id')]);
            CreditWallet::dispatch($order->id)->afterCommit();
            Mail::to('buyer@example.test')->queue(new PaidReceipt($order));
        });
    }

    public function refund(Order $order, int $amount): void
    {
        $this->ledger->post($order->wallet_id, $amount, 'refund of order ' . $order->id);
    }

    public function compensate(int $walletId, int $amount, string $ticket): void
    {
        $this->ledger->post($walletId, $amount, 'goodwill, ticket ' . $ticket);
    }

    public function quote(int $amount, string $currency): string
    {
        return number_format(intdiv($amount, 100)) . '.' . str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT) . ' ' . $currency;
    }
}
