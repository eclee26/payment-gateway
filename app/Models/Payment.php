<?php

namespace App\Models;

use App\Enums\CaptureMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasPrefixedId;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasPrefixedId;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'amount',
        'currency',
        'status',
        'capture_method',
        'amount_captured',
        'amount_refunded',
        'card_brand',
        'card_last4',
        'card_exp_month',
        'card_exp_year',
        'card_fingerprint',
        'failure_code',
        'description',
        'metadata',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'card_fingerprint',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'amount_captured' => 'integer',
            'amount_refunded' => 'integer',
            'card_exp_month' => 'integer',
            'card_exp_year' => 'integer',
            'status' => PaymentStatus::class,
            'capture_method' => CaptureMethod::class,
            'metadata' => 'array',
        ];
    }

    public static function idPrefix(): string
    {
        return 'pay_';
    }

    /**
     * The captured amount that has not yet been refunded.
     */
    public function refundableAmount(): int
    {
        return $this->amount_captured - $this->amount_refunded;
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
