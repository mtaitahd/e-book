<?php

namespace App\Http\Requests\Admin;

use App\Models\Book;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // A free book has no price to enter, so the field only becomes mandatory
        // once the admin has said the book is actually being sold. Everything
        // else about the amount is checked either way.
        $price = ['nullable', 'numeric', 'max:9999999.99'];

        if ($this->resolvedPricingType() === Book::PRICING_PAID) {
            $price[] = 'required';
            $price[] = 'min:0.01';
        }

        return [
            'title' => ['required', 'string', 'min:2', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('books', 'slug')->ignore($this->route('book'))],
            'description' => ['nullable', 'string', 'max:10000'],
            'pricing_type' => ['nullable', Rule::in(Book::PRICINGS)],
            'price' => $price,
            'author_ids' => ['required', 'array', 'min:1'],
            'author_ids.*' => ['integer', 'exists:authors,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
            'type' => ['nullable', Rule::in(Book::TYPES)],
            'book_format' => ['nullable', Rule::in(Book::FORMATS)],
            'ebook_file' => $this->fileRules(),
            'file_type' => ['nullable', 'string', Rule::in(['pdf', 'epub', 'mobi'])],
            'confirm_type_change' => ['nullable', 'boolean'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'status' => ['required', Rule::in(Book::STATUSES)],
        ];
    }

    /**
     * Rules for the uploaded digital file, which depend entirely on the type
     * the save is applying:
     *
     *  - E-Book: no file at all. Its content is chapters, so a stray upload
     *    must never quietly become the book's content (`prohibited`).
     *  - PDF: a real PDF. Required the first time a PDF book comes into
     *    existence - on creation, or when an existing book is switched to the
     *    PDF type - and optional afterwards so metadata edits never force the
     *    admin to hand over the same file again.
     *
     * A save that does not name a type is a legacy caller (an integration or
     * an older script) and keeps the historical, permissive rules.
     *
     * @return array<int, string>
     */
    private function fileRules(): array
    {
        $type = $this->submittedType();

        if ($type === Book::TYPE_EBOOK) {
            return ['prohibited'];
        }

        if ($type === Book::TYPE_PDF) {
            return $this->needsPdfUploadNow()
                ? ['required', 'file', 'mimes:pdf', 'max:10240']
                : ['nullable', 'file', 'mimes:pdf', 'max:10240'];
        }

        return ['nullable', 'file', 'mimes:pdf,epub,mobi', 'max:10240'];
    }

    /**
     * Whether this save must carry the PDF itself.
     *
     * Creation always does: a PDF book with no PDF is not a PDF book. An
     * existing book only has to when it is being switched over to the PDF
     * type and still has no file, because a metadata edit on an unfinished
     * draft must not become impossible.
     */
    private function needsPdfUploadNow(): bool
    {
        $book = $this->route('book');

        if (! $book instanceof Book) {
            return true;
        }

        return $this->submittedType() !== $book->type()
            && ($book->file_path === null || $book->file_path === '');
    }

    /**
     * The cross-field rules that cannot be expressed as a single field rule:
     * the PDF requirement on a type switch, and the safety rails around
     * changing a book's type at all.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $type = $this->submittedType();

            if ($type === null) {
                return;
            }

            $book = $this->route('book');

            if (! $book instanceof Book) {
                if ($type === Book::TYPE_PDF && ! $this->hasFile('ebook_file')) {
                    $validator->errors()->add(
                        'ebook_file',
                        'A PDF book needs its PDF uploaded before it can be created.',
                    );
                }

                return;
            }

            $currentType = $book->type();

            if ($type === $currentType) {
                return;
            }

            // Changing the type of a book people already own would swap the
            // reading experience underneath them, so it is refused outright.
            $owners = $book->purchases()->count();
            if ($owners > 0) {
                $validator->errors()->add(
                    'type',
                    'This book is owned by '.$owners.' customer(s), so its type can no longer be changed.',
                );

                return;
            }

            if (! filter_var($this->input('confirm_type_change'), FILTER_VALIDATE_BOOL)) {
                $validator->errors()->add(
                    'confirm_type_change',
                    'Confirm the type change to continue. Existing chapters and PDF files are always kept.',
                );
            }

            if ($type === Book::TYPE_PDF && ! $this->hasFile('ebook_file') && ($book->file_path ?? '') === '') {
                $validator->errors()->add(
                    'ebook_file',
                    'Upload this book\'s PDF before switching it to the PDF type.',
                );
            }
        });
    }

    /**
     * The book type this save is applying, or null when the caller did not
     * send one.
     */
    public function submittedType(): ?string
    {
        $type = strtolower(trim((string) $this->input('type')));

        return in_array($type, Book::TYPES, true) ? $type : null;
    }


    /**
     * The format this save is actually applying.
     *
     * When the request names a book type the format follows it: E-Book is
     * read chapter by chapter (`online`), PDF is read from the uploaded file
     * (`pdf`). The one exception is a book whose type is not changing - then
     * whatever it already stores is kept, so editing the metadata of a legacy
     * "PDF & Online" book never quietly demotes one of its two readers.
     *
     * A request with no type is a legacy caller and behaves exactly as it
     * always has: the submitted format, else the book's current format, else
     * PDF.
     */
    public function resolvedFormat(): string
    {
        $type = $this->submittedType();
        $book = $this->route('book');

        if ($type !== null) {
            if ($book instanceof Book && $book->type() === $type) {
                $current = strtolower(trim((string) $book->book_format));

                if (in_array($current, Book::FORMATS, true)) {
                    return $current;
                }
            }

            return $type === Book::TYPE_EBOOK ? Book::FORMAT_ONLINE : Book::FORMAT_PDF;
        }

        $format = $this->input('book_format');

        if (is_string($format) && $format !== '') {
            return $format;
        }

        if ($book instanceof Book && is_string($book->book_format) && $book->book_format !== '') {
            return $book->book_format;
        }

        return Book::FORMAT_PDF;
    }

    /**
     * The pricing type this save is actually applying.
     *
     * An absent `pricing_type` is not an error: it is read from the submitted
     * amount, so a request that only knows about `price` keeps the meaning it
     * always had. Anything above zero is a paid book, anything else is free.
     */
    public function resolvedPricingType(): string
    {
        $type = strtolower(trim((string) $this->input('pricing_type')));

        if (in_array($type, Book::PRICINGS, true)) {
            return $type;
        }

        $price = $this->input('price');

        return is_numeric($price) && (float) $price > 0 ? Book::PRICING_PAID : Book::PRICING_FREE;
    }

    /**
     * The amount to store. A free book is always worth exactly zero, whatever
     * the form still had sitting in the price box, so the stored price can
     * never contradict the pricing type beside it.
     */
    public function resolvedPrice(): string
    {
        if ($this->resolvedPricingType() === Book::PRICING_FREE) {
            return '0.00';
        }

        return number_format((float) $this->input('price'), 2, '.', '');
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [
            'author_ids.required' => 'Select at least one author.',
            'author_ids.min' => 'Select at least one author.',
            'author_ids.*.exists' => 'One of the selected authors is invalid.',
            'category_ids.*.exists' => 'One of the selected categories is invalid.',
            'cover_image.image' => 'The cover must be a valid image file.',
            'cover_image.mimes' => 'The cover must be a JPG, PNG or WebP image.',
            'cover_image.max' => 'The cover must not be larger than 2 MB.',
            'ebook_file.max' => 'The digital file must not be larger than 10 MB.',
            'ebook_file.prohibited' => 'An E-Book is written chapter by chapter and does not use an uploaded file. Remove it, or create the book as a PDF instead.',
            'book_format.in' => 'Choose PDF, Online or PDF & Online.',
            'type.in' => 'Choose either E-Book or PDF.',
            'pricing_type.in' => 'Choose whether this book is free or paid.',
            'price.required' => 'Enter the price of this book.',
            'price.numeric' => 'The price must be a number.',
            'price.min' => 'The price must be at least 0.01.',
            'price.max' => 'The price is too large.',
        ];

        if ($this->submittedType() === Book::TYPE_PDF) {
            $messages['ebook_file.required'] = 'Upload the complete PDF of this book.';
            $messages['ebook_file.mimes'] = 'The uploaded file must be a PDF.';
        } else {
            $messages['ebook_file.mimes'] = 'The digital file must be a PDF, EPUB or MOBI.';
        }

        return $messages;
    }
}
