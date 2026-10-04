<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Models\LedgerEntry;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

/**
 * The only class allowed to change a wallet balance: every change is an entry.
 */
final class Ledger
{
    public function post(int $walletId, int $amount, string $reason): void
    {
        DB::transaction(function () use ($walletId, $amount, $reason): void {
            $wallet = Wallet::lockForUpdate()->findOrFail($walletId);
            LedgerEntry::create(['wallet_id' => $wallet->id, 'amount' => $amount, 'reason' => $reason]);
            $wallet->balance += $amount;
            $wallet->save();
        });
    }
}
