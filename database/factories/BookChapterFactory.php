<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\BookChapter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BookChapter>
 */
class BookChapterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'book_id' => Book::factory(),
            'title' => rtrim($title, '.'),
            'slug' => Str::slug($title),
            'content' => '<p>'.fake()->paragraph().'</p>'
                .'<p>'.fake()->paragraph().'</p>',
            'position' => fake()->numberBetween(1, 50),
            'is_free' => false,
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_free' => true,
        ]);
    }
}
