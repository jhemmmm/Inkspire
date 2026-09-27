<?php

namespace App\Http\Requests\Admin;

use App\Concerns\ProfileValidationRules;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->targetUser());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * An Admin may not change their own role: demoting the only Admin
     * would lock everyone out of this portal. The password is optional --
     * left blank, the current one is kept.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $target = $this->targetUser();

        $roleRules = ['required', Rule::enum(UserRole::class)];

        if ($this->user()?->is($target)) {
            $roleRules[] = Rule::in([$target->role->value]);
        }

        return [
            ...$this->profileRules($target->id),
            'role' => $roleRules,
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => __('You cannot change your own role.'),
        ];
    }

    /**
     * The user being edited, resolved from the route.
     */
    private function targetUser(): User
    {
        /** @var User $user */
        $user = $this->route('user');

        return $user;
    }
}
