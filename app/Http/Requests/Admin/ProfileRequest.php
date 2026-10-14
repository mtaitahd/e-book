<?php

namespace App\Http\Requests\Admin;

use App\Support\Phone;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'phone' => [
                'nullable',
                'string',
                'max:32',
                Rule::unique('users', 'phone')->ignore($userId),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (Phone::normalizeTanzanian((string) $value) === null) {
                        $fail('Enter a valid Tanzanian mobile number, for example 07XXXXXXXXX.');
                    }
                },
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'current_password' => ['required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
        ];
    }

    /**
     * Normalize the mobile number so the unique check compares canonical
     * 255XXXXXXXXX values, exactly as registration does.
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
     * The canonical mobile number to store, or null when none was given.
     */
    public function normalizedPhone(): ?string
    {
        return Phone::normalizeTanzanian((string) $this->input('phone', ''));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Your name is required.',
            'email.required' => 'Your email address is required.',
            'email.unique' => 'That email address is already in use.',
            'current_password.required_with' => 'Enter your current password to set a new one.',
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.confirmed' => 'The new password confirmation does not match.',
            'avatar.image' => 'The profile photo must be an image.',
            'avatar.mimes' => 'The profile photo must be a JPG, PNG or WebP file.',
            'avatar.max' => 'The profile photo may not be larger than 2 MB.',
        ];
    }
}
