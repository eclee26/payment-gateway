<?php

namespace App\Services\Processor;

/**
 * A verified notification from a provider about the outcome of a payment
 * that was waiting on customer action.
 */
final class ProviderCallback
{
    public function __construct(
        public readonly string $providerReference,
        public readonly bool $succeeded,
        public readonly ?string $declineCode = null,
    ) {}
}
