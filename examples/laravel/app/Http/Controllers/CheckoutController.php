<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\CreditWallet;
use App\Mail\Receipt;
use App\Models\Order;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

/**
 * The checkout as it is often first written. Each method has one bug money-lint
 * reports; SafeCheckoutController is the same code fixed.
 */
final class CheckoutController
{
    public function pay(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->update(['status' => 'paid']);
            Http::post('https://psp.example/charges', ['amount' => $order->amount]);
            CreditWallet::dispatch($order->id);
            Mail::to('buyer@example.test')->queue(new Receipt($order));
        });
    }

    public function refund(Order $order, int $amount): void
    {
        $wallet = Wallet::find($order->wallet_id);
        $wallet->balance += $amount;
        $wallet->save();
    }

    public function compensate(int $walletId, int $amount): void
    {
        DB::table('wallets')->where('id', $walletId)->increment('balance', $amount);
    }

    public function quote(float $amount, string $currency): string
    {
        return number_format($amount, 2) . ' ' . $currency;
    }
}
