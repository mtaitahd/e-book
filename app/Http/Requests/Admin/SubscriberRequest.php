<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SubscriberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Normalise the address before validating.
     *
     * The model lower-cases and trims on save, so doing the same here keeps the
     * uniqueness check comparing exactly the value that will be stored. Without
     * this, "Jane@Example.com" would be validated against a stored
     * "jane@example.com" only by luck of the database collation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:subscribers,email'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'An email address is required.',
            'email.email' => 'That does not look like a valid email address.',
            'email.max' => 'The email address must not exceed 255 characters.',
            'email.unique' => 'That email address is already on the list.',
        ];
    }
}
