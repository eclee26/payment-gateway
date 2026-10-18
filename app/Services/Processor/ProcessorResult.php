<?php

namespace App\Services\Processor;

final class ProcessorResult
{
    /**
     * @param  array<string, mixed>|null  $nextAction
     */
    private function __construct(
        public readonly bool $approved,
        public readonly ?string $declineCode = null,
        public readonly ?string $providerReference = null,
        public readonly ?array $nextAction = null,
    ) {}

    public static function approved(?string $providerReference = null): self
    {
        return new self(true, providerReference: $providerReference);
    }

    public static function declined(string $code, ?string $providerReference = null): self
    {
        return new self(false, $code, $providerReference);
    }

    /**
     * Accepted by the provider, but the customer still has to act (scan a
     * QR code, approve in a wallet, pay at an outlet). The provider reports
     * the outcome later through a callback.
     *
     * @param  array<string, mixed>  $nextAction
     */
    public static function requiresAction(string $providerReference, array $nextAction): self
    {
        return new self(true, null, $providerReference, $nextAction);
    }

    public function isPendingAction(): bool
    {
        return $this->nextAction !== null;
    }
}
