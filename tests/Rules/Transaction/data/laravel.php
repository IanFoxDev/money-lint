<?php

declare(strict_types=1);

namespace TxLaravel;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

final class Cache
{
    public function transaction(\Closure $callback): mixed
    {
        return $callback();
    }
}

/**
 * @param list<int> $orders
 */
function capture(ConnectionInterface $db, PendingRequest $psp, array $orders, Cache $cache): void
{
    DB::transaction(function (): void {
        Http::post('https://psp.test/charges', ['amount' => 1000]);
        Mail::raw('Payment received', static function (): void {});
    });

    $db->transaction(function () use ($psp): void {
        $psp->post('https://psp.test/charges');
        Http::withToken('secret')->post('https://psp.test/refunds');
    });

    DB::transaction(static fn() => Http::get('https://psp.test/charges/1'));

    DB::transaction(function () use ($orders): void {
        array_map(static fn(int $id) => Http::post('https://psp.test/capture/' . $id), $orders);
    });

    \DB::transaction(function (): void {
        \Http::post('https://psp.test/charges');
    });

    // Not reported.
    Http::post('https://psp.test/charges');

    DB::transaction(function (): void {
        DB::afterCommit(static fn() => Http::post('https://psp.test/charges'));
        DB::table('orders')->update(['status' => 'paid']);
    });

    // A transaction() that is not a database transaction.
    $cache->transaction(static fn() => Http::post('https://psp.test/charges'));

    // A callback the transaction may call later, or never: not followed.
    DB::transaction(function () use ($orders): void {
        collect($orders)->each(static fn(int $id) => Http::post('https://psp.test/capture/' . $id));
    });

    $send = static function (): void {
        Http::post('https://psp.test/charges');
    };
    DB::transaction($send);
}
