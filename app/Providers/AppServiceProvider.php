<?php

namespace App\Providers;

use App\Services\Processor\PaymentProcessor;
use App\Services\Processor\SimulatedProcessor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
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

        // Turbo keeps the page's JS/CSS across navigations; when a new build
        // changes these tags, force a full reload so stale assets aren't used.
        Vite::useScriptTagAttributes(['data-turbo-track' => 'reload']);
        Vite::useStyleTagAttributes(['data-turbo-track' => 'reload']);

        // Runs after VerifyHmacSignature, so requests are counted per merchant.
        RateLimiter::for('api', function (Request $request) {
            $merchant = $request->attributes->get('merchant');

            return Limit::perMinute(config('gateway.rate_limit'))
                ->by($merchant ? 'merchant:'.$merchant->id : 'ip:'.$request->ip());
        });
    }
}
