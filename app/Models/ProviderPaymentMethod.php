<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\ProviderPaymentMethodFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment method a provider offers, with that provider's pricing.
 */
class ProviderPaymentMethod extends Model
{
    /** @use HasFactory<ProviderPaymentMethodFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'payment_method',
        'fee_bps',
        'fee_fixed',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'fee_bps' => 'integer',
            'fee_fixed' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The fee for a payment of this amount, in minor units: a percentage
     * (basis points, rounded half up) plus a fixed amount.
     */
    public function feeFor(int $amount): int
    {
        return intdiv($amount * $this->fee_bps + 5_000, 10_000) + $this->fee_fixed;
    }

    /**
     * Human-readable pricing, e.g. "2.5% + 1500 minor units".
     */
    public function pricingLabel(): string
    {
        $percent = rtrim(rtrim(number_format($this->fee_bps / 100, 2), '0'), '.').'%';

        return $this->fee_fixed > 0 ? "{$percent} + {$this->fee_fixed} minor units" : $percent;
    }

    /**
     * @return BelongsTo<Provider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
