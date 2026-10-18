<?php

namespace App\Http\Requests\Admin;

use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role;

/**
 * Create or update a custom role. Route middleware checks roles.create /
 * roles.update; this request stops a role from granting more than the
 * person editing it holds.
 */
class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => [
                'required', 'string', 'max:64', 'regex:/^[A-Za-z0-9][A-Za-z0-9 _.-]*$/',
                Rule::notIn(Permissions::SYSTEM_ROLES),
                Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role instanceof Role ? $role->getKey() : null),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Permissions::all())],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! Permissions::holdsAll($this->user(), $this->input('permissions') ?? [])) {
                    $validator->errors()->add('permissions', 'You can only grant permissions you hold yourself.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.not_in' => 'That name is reserved for a system role.',
            'name.regex' => 'Use letters, numbers, spaces, dots, dashes or underscores.',
        ];
    }
}
