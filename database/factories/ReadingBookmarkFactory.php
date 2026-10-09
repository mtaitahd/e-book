<?php

namespace Database\Factories;

use App\Models\Purchase;
use App\Models\ReadingBookmark;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReadingBookmark>
 */
class ReadingBookmarkFactory extends Factory
{
    /**
     * A bookmark is only meaningful inside a purchase: the ownership chain
     * (user -> purchase -> book) has to line up or the reader's own checks would
     * reject it. The foreign keys are therefore placeholders here and MUST be
     * supplied by forPurchase(); a create() without it will fail on a not-null
     * constraint rather than silently persisting a row nobody can read.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $page = fake()->numberBetween(1, 20);

        return [
            'user_id' => null,
            'purchase_id' => null,
            'book_id' => null,
            'chapter_id' => null,
            'page' => $page,
            'progress_percent' => fake()->randomFloat(2, 0, 100),
            'label' => fake()->optional()->sentence(3),
            'position_key' => ReadingBookmark::positionKeyFor(null, $page),
        ];
    }

    /**
     * A bookmark owned by the given purchase, optionally positioned inside one
     * of that purchase's chapters.
     */
    public function forPurchase(Purchase $purchase, int $page = 1, ?string $label = null, ?int $chapterId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $purchase->user_id,
            'purchase_id' => $purchase->id,
            'book_id' => $purchase->book_id,
            'chapter_id' => $chapterId,
            'page' => $page,
            'label' => $label,
            'position_key' => ReadingBookmark::positionKeyFor($chapterId, $page),
        ]);
    }
}
