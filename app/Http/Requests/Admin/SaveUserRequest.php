<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\Permissions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

/**
 * Create or update a user and their single role. Route middleware checks
 * users.create / users.update; this request stops privilege escalation.
 */
class SaveUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->targetUser();

        return $target === null || Permissions::canManageUser($this->user(), $target);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $target = $this->targetUser();

        // Nobody changes their own role, so they can't lock themselves out
        // or promote themselves.
        $editingSelf = $target !== null && $target->is($this->user());

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($target)],
            'password' => [$target === null ? 'required' : 'nullable', 'confirmed', Password::defaults()],
            'role' => $editingSelf
                ? ['exclude']
                : ['required', 'string', Rule::in(Permissions::assignableRoles($this->user())->pluck('name'))],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $target = $this->targetUser();

                if ($target !== null && $this->has('role') && $this->input('role') !== Permissions::SUPER_ADMIN
                    && Permissions::isLastSuperAdmin($target)) {
                    $validator->errors()->add('role', 'This is the only super-admin. Make someone else a super-admin first.');
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
            'role.in' => 'You cannot assign this role.',
        ];
    }

    private function targetUser(): ?User
    {
        $user = $this->route('user');

        return $user instanceof User ? $user : null;
    }
}
