<?php

declare(strict_types=1);

namespace RaceFixtures;

use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping as ORM;
use IanFoxDev\MoneyLint\Attribute\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $balance
 * @property int $visits
 */
final class Wallet extends Model {}

final class Account
{
    #[Money]
    public int $funds = 0;
}

final class VersionedAccount
{
    #[Money]
    public int $funds = 0;

    #[ORM\Version]
    public int $version = 1;
}

final class Payments
{
    public function debit(int $id, int $amount): void
    {
        $wallet = Wallet::find($id);
        $wallet->balance -= $amount;
        $wallet->save();
    }

    public function debitWhere(int $id, int $amount): void
    {
        $wallet = Wallet::where('id', $id)->firstOrFail();
        $wallet->balance = $wallet->balance - $amount;
        $wallet->save();
    }

    public function doctrine(EntityManagerInterface $em, int $id, int $amount): void
    {
        $account = $em->find(Account::class, $id);
        $account->funds += $amount;
        $em->flush();
    }

    public function repository(EntityManagerInterface $em, int $id): void
    {
        $account = $em->getRepository(Account::class)->findOneBy(['id' => $id]);
        $account->funds++;
        $em->flush();
    }

    // Not reported below.

    public function locked(int $id, int $amount): void
    {
        DB::transaction(function () use ($id, $amount): void {
            $wallet = Wallet::lockForUpdate()->find($id);
            $wallet->balance -= $amount;
            $wallet->save();
        });
    }

    public function lockedQuery(int $id, int $amount): void
    {
        $wallet = Wallet::query()->where('id', $id)->lockForUpdate()->first();
        $wallet->balance -= $amount;
        $wallet->save();
    }

    public function doctrineLocked(EntityManagerInterface $em, int $id): void
    {
        $account = $em->find(Account::class, $id, LockMode::PESSIMISTIC_WRITE);
        $account->funds += 1;
        $em->flush();
    }

    public function versioned(EntityManagerInterface $em, int $id): void
    {
        $account = $em->find(VersionedAccount::class, $id);
        $account->funds += 1;
        $em->flush();
    }

    public function overwrite(int $id, int $amount): void
    {
        $wallet = Wallet::find($id);
        $wallet->balance = $amount;
        $wallet->save();
    }

    public function notMoney(int $id): void
    {
        $wallet = Wallet::find($id);
        $wallet->visits++;
        $wallet->save();
    }

    public function notSaved(int $id): Wallet
    {
        $wallet = Wallet::find($id);
        $wallet->balance -= 1;

        return $wallet;
    }

    public function reloaded(int $id): void
    {
        $wallet = Wallet::find($id);
        $wallet = Wallet::lockForUpdate()->find($id);
        $wallet->balance -= 1;
        $wallet->save();
    }

    public function atomic(int $id): void
    {
        Wallet::whereKey($id)->decrement('balance', 1);
    }
}
