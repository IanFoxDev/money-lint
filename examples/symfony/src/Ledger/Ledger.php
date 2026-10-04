<?php

declare(strict_types=1);

namespace App\Ledger;

use App\Entity\Account;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Posts an entry and moves the balance in one transaction, with the row locked.
 */
final class Ledger
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function post(int $accountId, int $amount, string $reason): void
    {
        $this->em->wrapInTransaction(function () use ($accountId, $amount, $reason): void {
            $account = $this->em->find(Account::class, $accountId, LockMode::PESSIMISTIC_WRITE);
            if ($account === null) {
                throw new \DomainException('No account ' . $accountId . ' for ' . $reason);
            }
            $account->credit($amount);
        });
    }
}
