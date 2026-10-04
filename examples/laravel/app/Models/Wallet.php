<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $balance in minor units
 * @property string $currency
 */
final class Wallet extends Model
{
    protected $guarded = [];
}
