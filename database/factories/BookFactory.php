<?php

namespace Database\Factories;

use App\Models\Book;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        return [
            'isbn' => fake()->unique()->numerify('978-##########'),
            'title' => fake()->sentence(3),
            'author' => fake()->name(),
            'stock' => fake()->numberBetween(0, 20),
        ];
    }
}
