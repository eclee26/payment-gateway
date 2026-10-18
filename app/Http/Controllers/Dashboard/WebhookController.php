<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Rules\SafeWebhookUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class WebhookController extends Controller
{
    /**
     * Webhook settings and recent deliveries.
     */
    public function edit(Request $request): View
    {
        $merchant = $request->user()->merchant;

        return view('dashboard.webhooks.edit', [
            'merchant' => $merchant,
            'deliveries' => $merchant->webhookDeliveries()->latest()->latest('id')->limit(20)->get(),
        ]);
    }

    /**
     * Update (or clear) the webhook endpoint URL.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'webhook_url' => ['nullable', 'string', 'max:2048', 'url', new SafeWebhookUrl],
        ]);

        $request->user()->merchant->update(['webhook_url' => $validated['webhook_url'] ?? null]);

        return redirect()->route('dashboard.webhooks.edit')->with('status', 'Webhook URL saved.');
    }

    /**
     * Rotate the webhook signing secret and show the new one once.
     */
    public function rotateSecret(Request $request): Response
    {
        $merchant = $request->user()->merchant;
        $merchant->forceFill(['webhook_secret' => Merchant::generateWebhookSecret()])->save();

        return response()
            ->view('dashboard.webhooks.secret', ['secret' => $merchant->webhook_secret])
            ->header('Cache-Control', 'no-store');
    }
}
