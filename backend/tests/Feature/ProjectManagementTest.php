<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Project;
use App\Models\ProjectType;
use App\Models\Team;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $adminRole = Role::factory()->create(['slug' => 'admin']);
        $this->admin->roles()->attach($adminRole);

        $this->token = $this->admin->createToken('test-token')->plainTextToken;
    }

    /**
     * Test admin can create project
     */
    public function test_admin_can_create_project(): void
    {
        $projectType = ProjectType::factory()->create();
        $team = Team::factory()->create();

        $projectData = [
            'name' => 'New Project',
            'description' => 'Project description',
            'project_type_id' => $projectType->id,
            'team_id' => $team->id,
            'project_manager_id' => $this->admin->id,
            'status' => 'active',
            'priority' => 'high',
            'start_date' => now()->format('Y-m-d'),
        ];

        $response = $this->postJson('/api/projects', $projectData, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                ],
            ]);

        $this->assertDatabaseHas('projects', [
            'name' => 'New Project',
        ]);
    }

    /**
     * Test admin can list projects
     */
    public function test_admin_can_list_projects(): void
    {
        Project::factory()->count(5)->create();

        $response = $this->getJson('/api/projects', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                    ],
                ],
            ]);
    }

    /**
     * Test admin can update project
     */
    public function test_admin_can_update_project(): void
    {
        $project = Project::factory()->create();

        $updateData = [
            'name' => 'Updated Project Name',
            'description' => $project->description,
            'status' => 'completed',
            'priority' => $project->priority,
        ];

        $response = $this->putJson("/api/projects/{$project->id}", $updateData, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'status' => 'completed',
        ]);
    }
}
