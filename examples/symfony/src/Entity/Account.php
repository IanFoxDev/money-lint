<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use IanFoxDev\MoneyLint\Attribute\Money;

#[ORM\Entity]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    /** In minor units. Changed only by credit() and debit(), which the ledger calls. */
    #[ORM\Column]
    #[Money]
    private int $balance = 0;

    public function credit(int $amount): void
    {
        $this->balance += $amount;
    }

    public function balance(): int
    {
        return $this->balance;
    }
}
