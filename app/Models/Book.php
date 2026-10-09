<?php

namespace App\Models;

use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
        self::STATUS_ARCHIVED,
    ];

    /** A downloadable PDF read through the bundled PDF.js reader. */
    public const FORMAT_PDF = 'pdf';

    /** A native HTML/rich-text e-book read chapter by chapter in the browser. */
    public const FORMAT_ONLINE = 'online';

    /** Ships both a PDF file and native chapters. */
    public const FORMAT_BOTH = 'both';

    public const FORMATS = [
        self::FORMAT_PDF,
        self::FORMAT_ONLINE,
        self::FORMAT_BOTH,
    ];

    public const FORMAT_LABELS = [
        self::FORMAT_PDF => 'PDF',
        self::FORMAT_ONLINE => 'Online',
        self::FORMAT_BOTH => 'PDF & Online',
    ];

    /**
     * A chapter-based digital book: admin-written chapters read in the 3D
     * book reader. This is the content model, not the presence of a file.
     */
    public const TYPE_EBOOK = 'ebook';

    /** A single uploaded PDF read through the protected PDF.js reader. */
    public const TYPE_PDF = 'pdf';

    public const TYPES = [
        self::TYPE_EBOOK,
        self::TYPE_PDF,
    ];

    public const TYPE_LABELS = [
        self::TYPE_EBOOK => 'E-Book',
        self::TYPE_PDF => 'PDF',
    ];

    /**
     * The book costs nothing. Claimed straight into the customer's library,
     * never through the cart or a payment.
     */
    public const PRICING_FREE = 'free';

    /** The book is sold at the amount entered in `books.price`. */
    public const PRICING_PAID = 'paid';

    public const PRICINGS = [
        self::PRICING_FREE,
        self::PRICING_PAID,
    ];

    public const PRICING_LABELS = [
        self::PRICING_FREE => 'Free',
        self::PRICING_PAID => 'Paid',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'price',
        'pricing_type',
        'cover_image',
        'file_path',
        'file_type',
        'book_format',
        'type',
        'publisher',
        'published_at',
        'status',
    ];

    /**
     * Keep `type` and `book_format` in step on every save.
     *
     * `book_format` decides how a book behaves (which reader opens, whether a
     * download is offered), so it stays the behavioural truth and `type` is
     * always re-derived from it - that way the stored classification can never
     * drift into claiming a book is one thing while the system serves the
     * other. The one exception is a save that names a type and nothing else:
     * then the format is derived from that type, so writing
     * `['type' => 'ebook']` is enough to produce a chapter book.
     */
    protected static function booted(): void
    {
        static::saving(function (self $book): void {
            $typeGiven = $book->isDirty('type');
            $formatGiven = $book->isDirty('book_format');
            $namedType = strtolower(trim((string) ($book->getAttributes()['type'] ?? '')));

            // Only a real, named type may derive a format. A missing or junk
            // value (a legacy caller, a bad row) leaves the format alone and
            // is re-classified from it below instead.
            if ($typeGiven && ! $formatGiven && in_array($namedType, self::TYPES, true)) {
                $book->book_format = $namedType === self::TYPE_EBOOK
                    ? self::FORMAT_ONLINE
                    : self::FORMAT_PDF;
            }

            // Only the ambiguous legacy `both` format has to ask the database
            // about chapters, so a normal book save runs no extra query.
            $format = (string) $book->book_format;
            $hasChapters = strtolower(trim($format)) === self::FORMAT_BOTH
                && $book->chaptersExistForType();

            $book->type = self::typeForFormat($format, $hasChapters);
        });
    }


    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Native reader chapters, always in the order the reader should show them.
     */
    public function chapters(): HasMany
    {
        return $this->hasMany(BookChapter::class)->orderBy('position')->orderBy('id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * The stored format, defensively normalised. Unknown or missing values fall
     * back to PDF so a bad row can never disable the existing reader/download.
     */
    public function format(): string
    {
        $format = strtolower(trim((string) $this->book_format));

        return in_array($format, self::FORMATS, true) ? $format : self::FORMAT_PDF;
    }

    public function formatLabel(): string
    {
        return self::FORMAT_LABELS[$this->format()];
    }

    public function isPdfFormat(): bool
    {
        return in_array($this->format(), [self::FORMAT_PDF, self::FORMAT_BOTH], true);
    }

    public function isOnlineFormat(): bool
    {
        return in_array($this->format(), [self::FORMAT_ONLINE, self::FORMAT_BOTH], true);
    }

    /**
     * The content type a stored format classifies as.
     *
     * The legacy `both` format is the only ambiguous one - it can open either
     * reader - so it is settled by what the book can actually read today: with
     * chapters it opens the chapter reader, otherwise the PDF reader. Any
     * unknown format behaves like PDF, matching {@see self::format()}.
     */
    public static function typeForFormat(string $format, bool $hasChapters): string
    {
        $format = strtolower(trim($format));

        return match ($format) {
            self::FORMAT_ONLINE => self::TYPE_EBOOK,
            self::FORMAT_BOTH => $hasChapters ? self::TYPE_EBOOK : self::TYPE_PDF,
            default => self::TYPE_PDF,
        };
    }

    /**
     * The stored book type, defensively normalised. A bad row can never claim
     * to be an E-Book while the system serves it as a PDF (or the reverse):
     * anything unknown is re-classified from the format it is stored with.
     */
    public function type(): string
    {
        $type = strtolower(trim((string) $this->type));

        if (in_array($type, self::TYPES, true)) {
            return $type;
        }

        return self::typeForFormat($this->format(), $this->chaptersExistForType());
    }

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type()];
    }

    /** Chapter-based content, read in the digital book reader. */
    public function isEbookType(): bool
    {
        return $this->type() === self::TYPE_EBOOK;
    }

    /** One uploaded PDF, read through the protected PDF.js reader. */
    public function isPdfType(): bool
    {
        return $this->type() === self::TYPE_PDF;
    }

    /**
     * Whether this book owns at least one chapter right now. Asked of the
     * database only for saved books, so saving a brand new one stays free of
     * a pointless query.
     */
    private function chaptersExistForType(): bool
    {
        if (! $this->exists) {
            return false;
        }

        return $this->relationLoaded('chapters')
            ? $this->chapters->isNotEmpty()
            : $this->chapters()->exists();
    }


    /**
     * Whether this book can actually be read online right now: the format has
     * to allow it *and* at least one chapter must exist. An "online" book with
     * no chapters is an incomplete draft, not a reader error.
     */
    public function hasOnlineReading(): bool
    {
        if (! $this->isOnlineFormat()) {
            return false;
        }

        return $this->relationLoaded('chapters')
            ? $this->chapters->isNotEmpty()
            : $this->chapters()->exists();
    }

    /**
     * Whether a PDF file is attached and the format permits downloading it.
     */
    public function hasPdfFile(): bool
    {
        return $this->isPdfFormat()
            && $this->file_path !== null
            && $this->file_path !== '';
    }

    /**
     * The first chapter a visitor may read without buying the book.
     */
    public function freeChapter(): ?BookChapter
    {
        if (! $this->isOnlineFormat()) {
            return null;
        }

        if ($this->relationLoaded('chapters')) {
            return $this->chapters->firstWhere('is_free', true);
        }

        return $this->chapters()->where('is_free', true)->first();
    }

    /**
     * The stored pricing type, defensively normalised. Anything unknown or
     * missing is treated as paid, because wrongly calling a paid book free would
     * hand out content for nothing.
     */
    public function pricingType(): string
    {
        $type = strtolower(trim((string) $this->pricing_type));

        return in_array($type, self::PRICINGS, true) ? $type : self::PRICING_PAID;
    }

    public function isFree(): bool
    {
        return $this->pricingType() === self::PRICING_FREE;
    }

    public function isPaid(): bool
    {
        return ! $this->isFree();
    }

    public function pricingLabel(): string
    {
        return self::PRICING_LABELS[$this->pricingType()];
    }

    /**
     * The amount a customer is actually charged.
     *
     * Always 0.00 for a free book, whatever happens to be stored in `price`.
     * Cart and checkout totals are built from this, so a book can never
     * contribute money to an order while being advertised as free.
     */
    public function salePrice(): string
    {
        return $this->isFree() ? '0.00' : (string) $this->price;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }
}
