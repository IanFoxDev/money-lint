<?php

declare(strict_types=1);

namespace TxJobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

final class CreditWallet implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
}

final class CreditWalletAfterCommit implements ShouldQueueAfterCommit
{
    use Dispatchable;
    use Queueable;
}

final class CreditWalletConfigured implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }
}

final class RecalculateTotals
{
    use Dispatchable;
}

final class Receipt extends Mailable {}

function pay(Dispatcher $bus): void
{
    DB::transaction(function () use ($bus): void {
        dispatch(new CreditWallet());
        CreditWallet::dispatch();
        Bus::dispatch(new CreditWallet());
        $bus->dispatch(new CreditWallet());
        Mail::to('buyer@example.test')->queue(new Receipt());
        Mail::later(60, new Receipt());
        Queue::push(new CreditWallet());
        dispatch(static function (): void {});

        // Not reported: they wait for the commit, or do not go to the queue.
        dispatch(new CreditWallet())->afterCommit();
        CreditWallet::dispatch()->onQueue('payments')->afterCommit();
        dispatch((new CreditWallet())->afterCommit());
        dispatch(new CreditWalletAfterCommit());
        CreditWalletAfterCommit::dispatch();
        dispatch(new CreditWalletConfigured());
        dispatch(new RecalculateTotals());
        Bus::dispatch(new RecalculateTotals());
        Bus::dispatch(static function (): void {});
        dispatch(new CreditWallet())->beforeCommit();
    });

    // Not reported: outside the transaction.
    dispatch(new CreditWallet());
}
