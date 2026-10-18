<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\ApiDocSamples;
use App\Support\PostmanCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocsController extends Controller
{
    /**
     * API documentation with samples pre-filled for the merchant. Only the
     * public key ID is used; secrets never appear on this page.
     */
    public function show(Request $request): View
    {
        return view('dashboard.docs', [
            'keyId' => $this->activeKeyId($request),
            'baseUrl' => $this->baseUrl(),
            'samples' => $this->samples($request),
        ]);
    }

    /**
     * Download a Postman collection pre-filled with the base URL and key ID.
     * The secret variable is left empty for the merchant to fill in.
     */
    public function postman(Request $request): JsonResponse
    {
        $collection = new PostmanCollection(
            $this->samples($request),
            $this->baseUrl(),
            $this->activeKeyId($request) ?? 'key_your_key_id',
        );

        return response()
            ->json($collection->toArray(), options: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            ->header('Content-Disposition', 'attachment; filename="payment-gateway.postman_collection.json"');
    }

    private function samples(Request $request): ApiDocSamples
    {
        return new ApiDocSamples($this->baseUrl(), $this->activeKeyId($request) ?? 'key_your_key_id');
    }

    /**
     * The merchant's newest active (public) key ID, if any.
     */
    private function activeKeyId(Request $request): ?string
    {
        return $request->user()->merchant->apiKeys()
            ->whereNull('revoked_at')
            ->latest()
            ->latest('id')
            ->value('key_id');
    }

    private function baseUrl(): string
    {
        return rtrim(config('app.url'), '/');
    }
}
