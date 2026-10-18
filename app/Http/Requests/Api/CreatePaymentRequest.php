<?php

namespace App\Http\Requests\Api;

use App\Enums\CaptureMethod;
use App\Enums\PaymentMethod;
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
        foreach (['currency', 'payment_method'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => strtoupper($this->input($field))]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $card = PaymentMethod::Card->value;

        return [
            'payment_method' => ['required', 'string', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'integer', 'min:'.config('gateway.min_amount'), 'max:'.config('gateway.max_amount')],
            'currency' => ['required', 'string', Rule::in(config('gateway.currencies'))],
            'capture_method' => ['sometimes', "prohibited_unless:payment_method,{$card}", Rule::enum(CaptureMethod::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array', 'max:20'],
            'metadata.*' => ['nullable', 'string', 'max:500'],
            'card' => ["required_if:payment_method,{$card}", "prohibited_unless:payment_method,{$card}", 'array:number,exp_month,exp_year,cvc'],
            'card.number' => ["required_if:payment_method,{$card}", 'string', new CardNumber],
            'card.exp_month' => ["required_if:payment_method,{$card}", 'integer', 'between:1,12'],
            'card.exp_year' => ["required_if:payment_method,{$card}", 'integer', 'between:'.now()->year.','.(now()->year + 20)],
            'card.cvc' => ["required_if:payment_method,{$card}", 'string', 'regex:/^\d{3,4}$/'],
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
                $method = PaymentMethod::tryFrom((string) $this->input('payment_method'));

                if ($method && ! $validator->errors()->has('currency') && ! in_array($this->input('currency'), $method->currencies(), true)) {
                    $validator->errors()->add('currency', "{$method->value} payments must be in: ".implode(', ', $method->currencies()).'.');
                }
            },
            function (Validator $validator) {
                if ($this->input('payment_method') !== PaymentMethod::Card->value || $validator->errors()->hasAny(['card.exp_month', 'card.exp_year'])) {
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
