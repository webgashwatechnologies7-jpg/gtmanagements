<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Team;
use App\Models\Project;
use App\Models\EodReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    /**
     * Test user can create EOD report
     */
    public function test_user_can_create_eod_report(): void
    {
        $project = Project::factory()->create();

        $reportData = [
            'date' => now()->format('Y-m-d'),
            'items' => [
                [
                    'project_id' => $project->id,
                    'work_summary' => 'Completed task 1',
                    'progress_percentage' => 100,
                    'regular_minutes' => 120,
                ],
            ],
        ];

        $response = $this->postJson('/api/eod-reports', $reportData, [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'date',
                ],
            ]);
    }

    /**
     * Test user can list their EOD reports
     */
    public function test_user_can_list_eod_reports(): void
    {
        EodReport::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/eod-reports', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'date',
                    ],
                ],
            ]);
    }

    /**
     * Test user can get weekly team report
     */
    public function test_user_can_get_weekly_team_report(): void
    {
        $team = Team::factory()->create();

        $response = $this->getJson("/api/reports/teams/{$team->id}/weekly", [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    }
}
