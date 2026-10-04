<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $wallet_id
 * @property int $amount in minor units
 * @property string $currency
 * @property string $status
 */
final class Order extends Model
{
    protected $guarded = [];
}
