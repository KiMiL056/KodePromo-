<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = [
        'campaign_id',
        'code',
        'discount_type',
        'discount_value',
        'max_discount',
        'min_order_amount',
        'quota_total',
        'quota_used',
        'max_uses_per_user',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'float',
            'max_discount' => 'float',
            'min_order_amount' => 'float',
            'quota_total' => 'integer',
            'quota_used' => 'integer',
            'max_uses_per_user' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }
}
