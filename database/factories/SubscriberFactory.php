<?php

namespace Database\Factories;

use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscriber>
 */
class SubscriberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'status' => Subscriber::STATUS_ACTIVE,
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn () => ['status' => Subscriber::STATUS_UNSUBSCRIBED]);
    }
}
