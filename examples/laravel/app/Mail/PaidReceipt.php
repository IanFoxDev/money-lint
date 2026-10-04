<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use Illuminate\Mail\Mailable;

/**
 * The receipt, queued only once the transaction that marks the order paid commits.
 * A mailable has no afterCommit() method; the property does the same.
 */
final class PaidReceipt extends Mailable
{
    /** @var bool */
    public $afterCommit = true;

    public function __construct(public readonly Order $order) {}
}
