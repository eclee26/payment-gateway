<?php

namespace App\Providers;

use App\Services\Processor\PaymentProcessor;
use App\Services\Processor\SimulatedProcessor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentProcessor::class, SimulatedProcessor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();

        // Runs after VerifyHmacSignature, so requests are counted per merchant.
        RateLimiter::for('api', function (Request $request) {
            $merchant = $request->attributes->get('merchant');

            return Limit::perMinute(config('gateway.rate_limit'))
                ->by($merchant ? 'merchant:'.$merchant->id : 'ip:'.$request->ip());
        });
    }
}
