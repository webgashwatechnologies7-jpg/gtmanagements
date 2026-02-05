<?php

namespace Database\Factories;

use App\Models\EodReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EodReport>
 */
class EodReportFactory extends Factory
{
    protected $model = EodReport::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => $this->faker->date(),
            'status' => $this->faker->randomElement(['draft', 'submitted', 'approved', 'rejected']),
        ];
    }
}
