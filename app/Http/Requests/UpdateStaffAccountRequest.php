<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateStaffAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('platform.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        /** @var User|null $account */
        $account = $this->route('user');
        $isEditingOwnAccount = $account?->is($this->user()) ?? false;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($account)],
            'password' => ['nullable', Password::defaults()],
            'password_confirmation' => ['required_with:password', 'same:password'],
            'current_password' => [Rule::requiredIf($isEditingOwnAccount), 'current_password'],
        ];
    }
}
