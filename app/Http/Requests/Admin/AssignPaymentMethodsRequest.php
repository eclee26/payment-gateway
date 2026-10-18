<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Models\ProviderPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Assign one provider per payment method to a merchant:
 * assignments[GCASH] = provider_payment_method id, or empty to unassign.
 */
class AssignPaymentMethodsRequest extends FormRequest
{
    /**
     * Access is enforced by the permission:merchant-providers.assign route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'assignments' => ['nullable', 'array:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'assignments.*' => ['nullable', 'integer'],
        ];
    }

    /**
     * Every chosen provider must actively offer exactly that method.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($this->assignments() as $method => $id) {
                    $valid = ProviderPaymentMethod::available()
                        ->whereKey($id)
                        ->where('payment_method', $method)
                        ->exists();

                    if (! $valid) {
                        $validator->errors()->add("assignments.{$method}", "The selected provider does not offer {$method}.");
                    }
                }
            },
        ];
    }

    /**
     * Chosen provider pricing row per payment method; unassigned methods omitted.
     *
     * @return array<string, int>
     */
    public function assignments(): array
    {
        return collect($this->input('assignments', []))
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
