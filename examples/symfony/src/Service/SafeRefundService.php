<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Refund;
use App\Ledger\Ledger;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * RefundService with the bugs fixed. money-lint reports nothing here.
 */
final class SafeRefundService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $psp,
        private readonly MailerInterface $mailer,
        private readonly Ledger $ledger,
    ) {}

    public function refund(Refund $refund): void
    {
        // The provider is called first, with an idempotency key, and the result is
        // stored in a short transaction. The email goes out after the commit.
        $this->psp->request('POST', 'https://psp.example/refunds', [
            'headers' => ['Idempotency-Key' => 'refund-' . $refund->id],
            'json' => ['amount' => $refund->amount],
        ]);
        $this->em->wrapInTransaction(function () use ($refund): void {
            $refund->status = 'sent';
        });
        $this->mailer->send((new Email())->to('buyer@example.test')->text('Your refund is on its way.'));
    }

    public function payPart(int $refundId, int $partAmount): void
    {
        $this->em->wrapInTransaction(function () use ($refundId, $partAmount): void {
            $refund = $this->em->find(Refund::class, $refundId, LockMode::PESSIMISTIC_WRITE);
            if ($refund === null) {
                return;
            }
            $refund->remainingAmount -= $partAmount;
            $this->em->flush();
        });
    }

    public function creditBack(Refund $refund): void
    {
        $this->ledger->post($refund->accountId, $refund->amount, 'refund ' . $refund->id);
    }

    public function percentOf(int $amount, int $percent): int
    {
        return intdiv($amount * $percent, 100);
    }
}
