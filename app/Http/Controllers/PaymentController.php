<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * Create a sample payment intent.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json([
            'id' => 'pay_'.Str::random(16),
            'amount' => $validated['amount'],
            'currency' => strtoupper($validated['currency']),
            'description' => $validated['description'] ?? null,
            'status' => 'pending',
            'created_at' => now()->toIso8601String(),
        ], 201);
    }

    /**
     * Retrieve a sample payment by ID.
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'id' => $id,
            'status' => 'succeeded',
        ]);
    }
}
