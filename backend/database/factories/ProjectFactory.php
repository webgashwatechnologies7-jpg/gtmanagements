<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectType;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'project_type_id' => ProjectType::factory(),
            'team_id' => Team::factory(),
            'project_manager_id' => User::factory(),
            'created_by' => User::factory(),
            'status' => $this->faker->randomElement(['planning', 'active', 'on_hold', 'completed', 'cancelled']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high']),
            'start_date' => $this->faker->date(),
            'completion_date' => null,
        ];
    }
}
