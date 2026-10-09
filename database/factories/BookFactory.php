<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Book>
 */
class BookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(4, true);

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 500, 9000),
            'pricing_type' => Book::PRICING_PAID,
            'cover_image' => null,
            'file_path' => null,
            'file_type' => 'pdf',
            'book_format' => Book::FORMAT_PDF,
            'publisher' => fake()->company(),
            'published_at' => now()->subDays(fake()->numberBetween(1, 365)),
            'status' => Book::STATUS_DRAFT,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Book::STATUS_PUBLISHED,
        ]);
    }

    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'book_format' => Book::FORMAT_ONLINE,
        ]);
    }

    public function both(): static
    {
        return $this->state(fn (array $attributes) => [
            'book_format' => Book::FORMAT_BOTH,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Book::STATUS_ARCHIVED,
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_type' => Book::PRICING_FREE,
            'price' => 0,
        ]);
    }

    public function priced(string|float|int $price): static
    {
        return $this->state(fn (array $attributes) => [
            'pricing_type' => Book::PRICING_PAID,
            'price' => $price,
        ]);
    }
}
