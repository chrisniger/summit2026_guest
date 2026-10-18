<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.manage');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        if ($this->isMethod('patch')) {
            return ['role' => ['required', Rule::in(array_keys(config('summit.roles')))], 'is_active' => ['required', 'boolean']];
        }

        return ['name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(array_keys(config('summit.roles')))]];
    }
}
