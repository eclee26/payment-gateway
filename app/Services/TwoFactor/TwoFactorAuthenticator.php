<?php

namespace App\Services\TwoFactor;

use App\Models\User;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) secrets, QR codes, code verification and recovery codes.
 */
class TwoFactorAuthenticator
{
    /** Accept one 30-second step either side of now for clock drift. */
    private const WINDOW = 1;

    public function __construct(private readonly Google2FA $engine) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->engine->getQRCodeUrl(config('app.name'), $user->email, $secret);
    }

    public function qrCodeSvg(User $user, string $secret): string
    {
        $svg = (new Writer(new ImageRenderer(
            new RendererStyle(192, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(15, 23, 42))),
            new SvgImageBackEnd,
        )))->writeString($this->otpauthUrl($user, $secret));

        // Drop the XML declaration so the SVG can be inlined in HTML.
        return trim(substr($svg, strpos($svg, "\n") + 1));
    }

    /**
     * Verify a 6-digit code. A code that has already been accepted for this
     * user is rejected, so an observed code cannot be replayed.
     */
    public function verify(User $user, string $code, ?string $secret = null): bool
    {
        $secret ??= $user->two_factor_secret;
        $code = preg_replace('/\s+/', '', $code);

        if ($secret === null || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        // With a non-null previous timestamp, verifyKeyNewer returns the
        // matched time step, which is recorded to block reuse.
        $timestamp = $this->engine->verifyKeyNewer($secret, $code, $this->lastUsedTimestamp($user) ?? 0, self::WINDOW);

        if ($timestamp === false) {
            return false;
        }

        Cache::put($this->cacheKey($user), $timestamp, now()->addMinutes(5));

        return true;
    }

    /**
     * @return list<string>
     */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /**
     * Use up a recovery code. Each code works once.
     */
    public function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $code = Str::lower(trim($code));

        $match = collect($codes)->first(fn (string $stored) => hash_equals($stored, $code));

        if ($match === null) {
            return false;
        }

        $user->forceFill([
            'two_factor_recovery_codes' => array_values(array_diff($codes, [$match])),
        ])->save();

        return true;
    }

    /**
     * Turn TOTP on with a secret the user has proven they hold.
     *
     * @return list<string> the new recovery codes, shown once
     */
    public function enable(User $user, string $secret): array
    {
        $codes = $this->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $codes;
    }

    /**
     * Clear TOTP so the user has to set it up again at their next request.
     */
    public function reset(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        Cache::forget($this->cacheKey($user));
    }

    private function lastUsedTimestamp(User $user): ?int
    {
        return Cache::get($this->cacheKey($user));
    }

    private function cacheKey(User $user): string
    {
        return 'two-factor:last-used:'.$user->getKey();
    }
}
