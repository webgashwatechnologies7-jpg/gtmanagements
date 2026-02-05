<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Team;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeamManagementTest extends TestCase
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
     * Test admin can create team
     */
    public function test_admin_can_create_team(): void
    {
        $teamData = [
            'name' => 'Development Team',
            'description' => 'Team for development projects',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/teams', $teamData, [
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

        $this->assertDatabaseHas('teams', [
            'name' => 'Development Team',
        ]);
    }

    /**
     * Test admin can list teams
     */
    public function test_admin_can_list_teams(): void
    {
        Team::factory()->count(3)->create();

        $response = $this->getJson('/api/teams', [
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
     * Test admin can update team
     */
    public function test_admin_can_update_team(): void
    {
        $team = Team::factory()->create();

        $updateData = [
            'name' => 'Updated Team Name',
            'description' => $team->description,
            'status' => 'active',
        ];

        $response = $this->putJson("/api/teams/{$team->id}", $updateData, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Updated Team Name',
        ]);
    }

    /**
     * Test admin can assign members to team
     */
    public function test_admin_can_assign_members_to_team(): void
    {
        $team = Team::factory()->create();
        $users = User::factory()->count(3)->create();

        $response = $this->postJson("/api/teams/{$team->id}/assign-members", [
            'members' => $users->pluck('id')->toArray(),
        ], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals(3, $team->fresh()->members()->count());
    }
}
