<?php

namespace Database\Factories;

use App\Models\ResearchJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResearchJob>
 */
class ResearchJobFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ResearchJob>
     */
    protected $model = ResearchJob::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'pending',
            'current_step' => null,
            'progress' => 0,
            'user_input' => fake()->sentence(12),
            'extracted_params' => null,
            'queries' => null,
            'sources' => null,
            'report' => null,
            'error' => null,
        ];
    }
}
