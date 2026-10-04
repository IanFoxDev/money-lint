<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $wallet_id
 * @property int $amount
 * @property string $reason
 */
final class LedgerEntry extends Model
{
    protected $guarded = [];
}
