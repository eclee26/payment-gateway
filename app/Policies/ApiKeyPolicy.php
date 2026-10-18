<?php

namespace App\Policies;

use App\Models\ApiKey;
use App\Models\User;

class ApiKeyPolicy
{
    /**
     * Determine whether the user can revoke the key.
     */
    public function delete(User $user, ApiKey $apiKey): bool
    {
        return $user->merchant !== null && $apiKey->merchant_id === $user->merchant->id;
    }
}
