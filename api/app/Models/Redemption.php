<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Redemption extends Model
{
    protected $fillable = [
        'voucher_id',
        'order_id',
        'user_identifier',
        'idempotency_key',
        'order_amount',
        'discount_amount',
        'final_amount',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'order_amount' => 'float',
            'discount_amount' => 'float',
            'final_amount' => 'float',
            'redeemed_at' => 'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
