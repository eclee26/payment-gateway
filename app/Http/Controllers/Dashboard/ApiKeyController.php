<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ApiKeyController extends Controller
{
    /**
     * List the merchant's API keys (secrets are never shown here).
     */
    public function index(Request $request): View
    {
        return view('dashboard.api-keys.index', [
            'keys' => $request->user()->merchant->apiKeys()->latest()->get(),
        ]);
    }

    /**
     * Issue a new key. The secret is rendered once in this response,
     * rather than flashed to the session, and is never retrievable again.
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $key = ApiKey::issue($request->user()->merchant, $validated['name'] ?? null);

        return response()
            ->view('dashboard.api-keys.created', ['key' => $key])
            ->header('Cache-Control', 'no-store');
    }

    /**
     * Revoke a key. Revoked keys can no longer sign requests.
     */
    public function destroy(Request $request, string $apiKey): RedirectResponse
    {
        $apiKey = $request->user()->merchant->apiKeys()->findOrFail($apiKey);

        Gate::authorize('delete', $apiKey);

        $apiKey->forceFill(['revoked_at' => now()])->save();

        return redirect()->route('dashboard.api-keys.index')->with('status', "Key {$apiKey->key_id} revoked.");
    }
}
