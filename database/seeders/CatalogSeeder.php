<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a small sample catalogue for development/demonstration.
     *
     * No data is copied from any existing database.
     */
    public function run(): void
    {
        $authorRows = [
            'Grace Nakato' => ['slug' => 'grace-nakato', 'bio' => 'Writer and lecturer focused on African technology adoption.'],
            'Brian Otieno' => ['slug' => 'brian-otieno', 'bio' => 'Software engineer and author of practical programming guides.'],
            'Amara Wanjiru' => ['slug' => 'amara-wanjiru', 'bio' => 'Business strategist writing on entrepreneurship and growth.'],
        ];

        $categoryRows = [
            'Programming' => ['slug' => 'programming', 'description' => 'Software development and engineering titles.'],
            'Business' => ['slug' => 'business', 'description' => 'Entrepreneurship, finance and management titles.'],
            'Fiction' => ['slug' => 'fiction', 'description' => 'Fictional stories and novels.'],
        ];

        foreach ($authorRows as $name => $data) {
            Author::create(['name' => $name, 'slug' => $data['slug'], 'bio' => $data['bio']]);
        }

        $categories = collect($categoryRows)->map(
            fn (array $data, string $name) => Category::create(['name' => $name, 'slug' => $data['slug'], 'description' => $data['description']])
        );

        $books = [
            [
                'title' => 'Modern Web Development', 'slug' => 'modern-web-development',
                'description' => 'A practical guide to building modern web applications with PHP and Laravel.',
                'price' => 1500.00, 'publisher' => 'TechServe Publishing', 'status' => Book::STATUS_PUBLISHED,
                'file_type' => 'pdf', 'authors' => ['Brian Otieno'], 'categories' => ['Programming'],
            ],
            [
                'title' => 'Grow Your Startup', 'slug' => 'grow-your-startup',
                'description' => 'Lessons on building and scaling a small business in an emerging market.',
                'price' => 1200.00, 'publisher' => 'Amara Press', 'status' => Book::STATUS_PUBLISHED,
                'file_type' => 'epub', 'authors' => ['Amara Wanjiru'], 'categories' => ['Business'],
            ],
            [
                'title' => 'Data Skills for Africa', 'slug' => 'data-skills-for-africa',
                'description' => 'An introduction to data literacy with local case studies.',
                'price' => 2000.00, 'publisher' => 'TechServe Publishing', 'status' => Book::STATUS_PUBLISHED,
                'file_type' => 'pdf', 'authors' => ['Grace Nakato', 'Brian Otieno'], 'categories' => ['Programming'],
            ],
            [
                'title' => 'Draft Technical Manual', 'slug' => 'draft-technical-manual',
                'description' => 'An unpublished sample title that must not appear on the public storefront.',
                'price' => 0.00, 'publisher' => null, 'status' => Book::STATUS_DRAFT,
                'file_type' => 'pdf', 'authors' => ['Grace Nakato'], 'categories' => ['Programming'],
            ],
        ];

        foreach ($books as $data) {
            $authorNames = $data['authors'];
            $categoryNames = $data['categories'];
            unset($data['authors'], $data['categories']);

            $book = Book::create($data);

            $book->authors()->sync(
                Author::whereIn('name', $authorNames)->pluck('id')
            );
            $book->categories()->sync(
                $categories->whereIn('name', $categoryNames)->pluck('id')
            );
        }
    }
}