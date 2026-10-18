<?php

namespace App\Models;

use Database\Factories\ApiKeyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiKey extends Model
{
    /** @use HasFactory<ApiKeyFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * The secret is encrypted rather than hashed because verifying an HMAC
     * signature requires the plaintext secret on the server.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Issue a new key for the merchant. The plaintext secret is only
     * available on the returned model instance and is never shown again.
     */
    public static function issue(Merchant $merchant, ?string $name = null): static
    {
        $key = new static(['name' => $name]);
        $key->merchant()->associate($merchant);
        $key->key_id = 'key_'.bin2hex(random_bytes(12));
        $key->secret = 'sk_'.bin2hex(random_bytes(32));
        $key->save();

        return $key;
    }

    /**
     * Whether the key has been revoked.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * @return BelongsTo<Merchant, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }
}
