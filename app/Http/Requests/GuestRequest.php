<?php

namespace App\Http\Requests;

use App\Models\GuestRecord;
use App\Services\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

class GuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('guest')
            ? $this->user()->can('update', $this->route('guest'))
            : $this->user()->can('create', GuestRecord::class);
    }

    public static function fields(): array
    {
        return [
            'conference_session_id' => 'required|integer|exists:conference_sessions,id',
            'full_name' => 'required|string|max:255',
            'phone_raw' => ['required', 'string', 'max:50', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! PhoneNumber::normalize($value)) {
                    $fail('Enter a valid phone number containing 7–15 digits.');
                }
            }],
            'first_name' => 'nullable|string|max:255', 'last_name' => 'nullable|string|max:255',
            'gender' => 'nullable|string|max:255', 'home_address' => 'nullable|string|max:5000',
            'heard_about' => 'nullable|string|max:255', 'entry_notes' => 'nullable|string|max:5000',
            'captured_at_original_utc' => ['nullable', 'date_format:Y-m-d\TH:i:sP', 'before_or_equal:now'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_map(fn ($value) => is_string($value) ? trim($value) : $value, $this->only(array_keys(self::fields()))));
    }

    public function rules(): array
    {
        return [...self::fields(), 'submission_id' => $this->isMethod('POST') ? 'required|uuid' : 'nullable|uuid',
            'version' => $this->isMethod('PATCH') ? 'required|integer|min:1' : 'nullable|integer',
            'keep_separate' => 'nullable|boolean', 'add_another' => 'nullable|boolean'];
    }
}
