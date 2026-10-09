<?php

namespace App\Http\Requests\Auth;

use App\Support\Phone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'phone'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (Phone::normalizeTanzanian((string) $value) === null) {
                        $fail('Enter a valid Tanzanian mobile number, for example 07XXXXXXXXX.');
                    }
                },
            ],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * Normalize the mobile money number before validation so the unique check
     * compares canonical 255XXXXXXXXX values. A number we cannot normalize is
     * left untouched, letting the rules above report the format problem against
     * what the customer actually typed.
     */
    protected function prepareForValidation(): void
    {
        $raw = trim((string) $this->input('phone', ''));

        if ($raw === '') {
            $this->merge(['phone' => null]);

            return;
        }

        $this->merge(['phone' => Phone::normalizeTanzanian($raw) ?? $raw]);
    }

    /**
     * The canonical mobile money number to store, or null when none was given.
     */
    public function normalizedPhone(): ?string
    {
        return Phone::normalizeTanzanian((string) $this->input('phone', ''));
    }
}
