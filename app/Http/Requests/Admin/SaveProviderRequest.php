<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Services\Processor\ProcessorManager;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create or update a provider and the payment methods it offers. Methods
 * are keyed by PaymentMethod value: methods[GCASH][enabled|fee_bps|fee_fixed].
 */
class SaveProviderRequest extends FormRequest
{
    /**
     * Access is enforced by the permission:providers.manage route middleware.
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
        $this->merge(['is_active' => $this->boolean('is_active')]);

        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtolower(trim($this->input('code')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $methods = array_column(PaymentMethod::cases(), 'value');

        // Code and driver are fixed once the provider exists: callbacks are
        // routed by code, and in-flight payments capture, cancel and refund
        // through the driver they were created with.
        $creating = $this->route('provider') === null;

        return [
            'code' => $creating
                ? ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('providers', 'code')]
                : ['exclude'],
            'name' => ['required', 'string', 'max:255'],
            'driver' => $creating
                ? ['required', 'string', Rule::in(ProcessorManager::drivers())]
                : ['exclude'],
            'is_active' => ['boolean'],
            'methods' => ['nullable', 'array:'.implode(',', $methods)],
            'methods.*' => ['array:enabled,fee_bps,fee_fixed'],
            'methods.*.enabled' => ['nullable', 'boolean'],
            'methods.*.fee_bps' => ['nullable', 'integer', 'between:0,10000'],
            'methods.*.fee_fixed' => ['nullable', 'integer', 'between:0,100000000'],
        ];
    }

    /**
     * Pricing per method, for every method the form submitted.
     *
     * @return array<string, array{is_active: bool, fee_bps: int, fee_fixed: int}>
     */
    public function methods(): array
    {
        return collect($this->validated('methods') ?? [])
            ->map(fn (array $row) => [
                'is_active' => (bool) ($row['enabled'] ?? false),
                'fee_bps' => (int) ($row['fee_bps'] ?? 0),
                'fee_fixed' => (int) ($row['fee_fixed'] ?? 0),
            ])
            ->all();
    }
}
