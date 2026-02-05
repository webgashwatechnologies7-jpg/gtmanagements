<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create admin user for testing
        $this->admin = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        // Create admin role
        $adminRole = Role::factory()->create(['slug' => 'admin']);
        $this->admin->roles()->attach($adminRole);

        $this->token = $this->admin->createToken('test-token')->plainTextToken;
    }

    /**
     * Test admin can list users
     */
    public function test_admin_can_list_users(): void
    {
        User::factory()->count(5)->create();

        $response = $this->getJson('/api/users', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                    ],
                ],
                'meta',
            ]);
    }

    /**
     * Test admin can create user
     */
    public function test_admin_can_create_user(): void
    {
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'employee_id' => 'EMP002',
            'phone' => '1234567890',
            'status' => 'active',
        ];

        $response = $this->postJson('/api/users', $userData, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
        ]);
    }

    /**
     * Test admin can update user
     */
    public function test_admin_can_update_user(): void
    {
        $user = User::factory()->create();

        $updateData = [
            'name' => 'Updated Name',
            'email' => $user->email,
            'phone' => '9876543210',
        ];

        $response = $this->putJson("/api/users/{$user->id}", $updateData, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    /**
     * Test admin can delete user
     */
    public function test_admin_can_delete_user(): void
    {
        $user = User::factory()->create();

        $response = $this->deleteJson("/api/users/{$user->id}", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    /**
     * Test user cannot delete themselves
     */
    public function test_user_cannot_delete_themselves(): void
    {
        $response = $this->deleteJson("/api/users/{$this->admin->id}", [], [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(403);
    }
}
