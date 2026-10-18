<?php

namespace App\Services\Processor;

use App\Models\Provider;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

/**
 * Resolves the driver that talks to a provider's API.
 */
class ProcessorManager
{
    public function __construct(private readonly Container $container) {}

    public function for(Provider $provider): PaymentProcessor
    {
        $class = config('gateway.provider_drivers')[$provider->driver] ?? null;

        if ($class === null) {
            throw new InvalidArgumentException("Unknown provider driver [{$provider->driver}].");
        }

        return $this->container->make($class, ['provider' => $provider]);
    }

    /**
     * Driver keys admins can choose from.
     *
     * @return list<string>
     */
    public static function drivers(): array
    {
        return array_keys(config('gateway.provider_drivers'));
    }
}
