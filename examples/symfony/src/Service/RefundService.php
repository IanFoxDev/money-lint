<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Refund;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Refunds as they are often first written. Each method has one bug money-lint
 * reports; SafeRefundService is the same code fixed.
 */
final class RefundService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $psp,
        private readonly MailerInterface $mailer,
    ) {}

    public function refund(Refund $refund): void
    {
        $this->em->wrapInTransaction(function () use ($refund): void {
            $refund->status = 'sent';
            $this->psp->request('POST', 'https://psp.example/refunds', ['json' => ['amount' => $refund->amount]]);
            $this->mailer->send((new Email())->to('buyer@example.test')->text('Your refund is on its way.'));
        });
    }

    public function payPart(int $refundId, int $partAmount): void
    {
        $refund = $this->em->find(Refund::class, $refundId);
        if ($refund === null) {
            return;
        }
        $refund->remainingAmount -= $partAmount;
        $this->em->flush();
    }

    public function creditBack(Refund $refund): void
    {
        $this->em->createQuery('UPDATE App\Entity\Account a SET a.balance = a.balance + :amount WHERE a.id = :id')
            ->execute(['amount' => $refund->amount, 'id' => $refund->accountId]);
    }

    public function percentOf(float $amount, int $percent): float
    {
        return $amount * $percent / 100;
    }
}
