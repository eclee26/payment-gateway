<?php

namespace App\Rules;

use App\Support\Card;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CardNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Card::passesLuhn(Card::normalize($value))) {
            $fail('The card number is invalid.');
        }
    }
}
