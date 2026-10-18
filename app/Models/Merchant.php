<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\MerchantFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Merchant extends Model
{
    /** @use HasFactory<MerchantFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'webhook_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'webhook_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'webhook_secret' => 'encrypted',
        ];
    }

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function (Merchant $merchant) {
            $merchant->webhook_secret ??= static::generateWebhookSecret();
        });
    }

    /**
     * Generate a new random webhook signing secret.
     */
    public static function generateWebhookSecret(): string
    {
        return 'whsec_'.bin2hex(random_bytes(24));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<ApiKey, $this>
     */
    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    /**
     * @return HasMany<MerchantPaymentMethod, $this>
     */
    public function paymentMethodAssignments(): HasMany
    {
        return $this->hasMany(MerchantPaymentMethod::class);
    }

    /**
     * The provider pricing row that handles this payment method for the
     * merchant, or null when it is unassigned or no longer available.
     */
    public function providerFor(PaymentMethod $method): ?ProviderPaymentMethod
    {
        return $this->availablePaymentMethodsQuery()
            ->where('payment_method', $method->value)
            ->first();
    }

    /**
     * Every assigned, available pricing row, keyed by payment method value.
     *
     * @return Collection<string, ProviderPaymentMethod>
     */
    public function availablePaymentMethods(): Collection
    {
        return $this->availablePaymentMethodsQuery()
            ->get()
            ->keyBy(fn (ProviderPaymentMethod $row) => $row->payment_method->value);
    }

    /**
     * @return Builder<ProviderPaymentMethod>
     */
    private function availablePaymentMethodsQuery(): Builder
    {
        // Matching the method on both sides guards against an assignment row
        // whose method disagrees with the pricing row it points at.
        return ProviderPaymentMethod::query()
            ->available()
            ->whereExists(fn ($query) => $query->from('merchant_payment_methods')
                ->whereColumn('merchant_payment_methods.provider_payment_method_id', 'provider_payment_methods.id')
                ->whereColumn('merchant_payment_methods.payment_method', 'provider_payment_methods.payment_method')
                ->where('merchant_payment_methods.merchant_id', $this->id))
            ->with('provider');
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
