<?php

namespace App\Models;

use Database\Factories\SubscriberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscriber extends Model
{
    /** @use HasFactory<SubscriberFactory> */
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'status',
        'subscribed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscribed_at' => 'datetime',
        ];
    }

    /**
     * Normalise the address on the way in.
     *
     * Trimming and lower-casing here means the value written to the unique
     * index is always in one canonical form, so a subscriber cannot be added
     * twice just by changing the capitalisation of the address.
     */
    public function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = mb_strtolower(trim((string) $value));
    }

    /**
     * Stamp the join date the first time an address becomes active.
     *
     * Re-subscribing keeps the original date rather than resetting it, so the
     * list records when someone first joined instead of when they last
     * toggled the button.
     */
    protected static function booted(): void
    {
        static::saving(function (self $subscriber): void {
            if ($subscriber->status === self::STATUS_ACTIVE && $subscriber->subscribed_at === null) {
                $subscriber->subscribed_at = now();
            }
        });
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeUnsubscribed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_UNSUBSCRIBED);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
