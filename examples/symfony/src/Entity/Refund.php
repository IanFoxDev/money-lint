<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Refund
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public int $accountId = 0;

    /** In minor units. */
    #[ORM\Column]
    public int $amount = 0;

    /** The amount still to pay back, in minor units. */
    #[ORM\Column]
    public int $remainingAmount = 0;

    #[ORM\Column]
    public string $status = 'requested';
}
