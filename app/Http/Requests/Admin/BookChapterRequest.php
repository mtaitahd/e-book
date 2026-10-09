<?php

namespace App\Http\Requests\Admin;

use App\Models\Book;
use App\Models\BookChapter;
use App\Services\HtmlSanitizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating and editing a native-reader chapter.
 *
 * The content is sanitized *before* validation so that a body made up entirely
 * of markup the allowlist rejects (e.g. only a <script> block) fails the
 * "required" rule instead of being stored as an empty chapter. The sanitized
 * value is what validation and persistence both see.
 */
class BookChapterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Replace the raw body with its sanitized form before anything is
     * validated, so the rest of the request can treat it as trusted.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('content')) {
            return;
        }

        $content = app(HtmlSanitizer::class)->sanitize((string) $this->input('content'));

        $this->merge(['content' => $content]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('book_chapters', 'slug')
                    ->where('book_id', $this->bookId())
                    ->ignore($this->route('chapter')),
            ],
            'content' => ['required', 'string', 'min:1', 'max:1000000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_free' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'content.required' => 'Chapter content cannot be empty. Paste the chapter text into the editor.',
            'content.max' => 'This chapter is too long. Split it into smaller chapters.',
            'slug.unique' => 'Another chapter of this book already uses that URL.',
            'position.integer' => 'Chapter position must be a whole number.',
        ];
    }

    /**
     * The book this chapter belongs to, which scopes the slug uniqueness rule.
     */
    private function bookId(): int|string|null
    {
        $book = $this->route('book');

        if ($book instanceof Book) {
            return $book->id;
        }

        $chapter = $this->route('chapter');

        return $chapter instanceof BookChapter ? $chapter->book_id : null;
    }
}
