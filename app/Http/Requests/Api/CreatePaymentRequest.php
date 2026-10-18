<?php

namespace App\Http\Requests\Api;

use App\Enums\CaptureMethod;
use App\Rules\CardNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreatePaymentRequest extends FormRequest
{
    /**
     * Authentication and ownership are enforced by VerifyHmacSignature.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:'.config('gateway.min_amount'), 'max:'.config('gateway.max_amount')],
            'currency' => ['required', 'string', Rule::in(config('gateway.currencies'))],
            'capture_method' => ['sometimes', Rule::enum(CaptureMethod::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array', 'max:20'],
            'metadata.*' => ['nullable', 'string', 'max:500'],
            'card' => ['required', 'array:number,exp_month,exp_year,cvc'],
            'card.number' => ['required', 'string', new CardNumber],
            'card.exp_month' => ['required', 'integer', 'between:1,12'],
            'card.exp_year' => ['required', 'integer', 'between:'.now()->year.','.(now()->year + 20)],
            'card.cvc' => ['required', 'string', 'regex:/^\d{3,4}$/'],
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['card.exp_month', 'card.exp_year'])) {
                    return;
                }

                $month = (int) $this->input('card.exp_month');
                $year = (int) $this->input('card.exp_year');

                if ($year === now()->year && $month < now()->month) {
                    $validator->errors()->add('card.exp_month', 'The card has expired.');
                }
            },
        ];
    }
}
