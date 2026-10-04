<?php

declare(strict_types=1);

namespace LedgerFixtures;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $balance
 * @property int $visits
 */
final class Wallet extends Model {}

final class Account
{
    private int $balance;

    public function __construct()
    {
        $this->balance = 0;
    }

    public function setBalance(int $balance): void
    {
        $this->balance = $balance;
    }

    public function balance(): int
    {
        return $this->balance;
    }
}

final class Ledger
{
    public function post(Wallet $wallet, int $amount): void
    {
        $wallet->increment('balance', $amount);
        $wallet->balance += $amount;
        DB::update('UPDATE wallets SET balance = balance + ? WHERE id = ?', [$amount, $wallet->getKey()]);
    }
}

final class CheckoutController
{
    public function pay(Wallet $wallet, Account $account, ConnectionInterface $db, EntityManagerInterface $em, int $amount): void
    {
        $wallet->balance -= $amount;
        $wallet->balance = 0;
        $wallet->increment('balance', $amount);
        $wallet->decrement('balance');
        $wallet->incrementEach(['balance' => 5]);
        $wallet->update(['balance' => 10]);
        Wallet::query()->where('id', 1)->increment('balance', 5);
        DB::table('wallets')->where('id', 1)->increment('balance', 5);
        $db->table('wallets')->update(['balance' => 0]);
        DB::update('UPDATE wallets SET balance = balance - ? WHERE id = ?', [$amount, 1]);
        $em->createQuery('UPDATE LedgerFixtures\Account a SET a.balance = a.balance + :amount')->execute();
        $account->setBalance(5);
        $wallet->increment('visits', 1, ['balance' => 0]);

        // Not reported: other columns and tables, reads.
        $wallet->increment('visits');
        $wallet->update(['visits' => 1]);
        DB::table('orders')->increment('balance');
        DB::update('UPDATE wallets SET balance_due = 0');
        DB::select('SELECT balance FROM wallets');
        $read = $wallet->balance + $account->balance();
    }
}
