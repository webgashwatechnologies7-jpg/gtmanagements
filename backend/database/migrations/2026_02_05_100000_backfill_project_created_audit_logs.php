<?php

use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Backfill audit log: add "created" entry for existing projects
     * so that history shows who created and when.
     */
    public function up(): void
    {
        $existingCreatedIds = AuditLog::where('entity_type', 'project')
            ->where('action', 'created')
            ->pluck('entity_id')
            ->unique()
            ->all();

        Project::whereNotIn('id', $existingCreatedIds)->each(function (Project $project) {
            AuditLog::create([
                'user_id' => $project->created_by,
                'action' => 'created',
                'entity_type' => 'project',
                'entity_id' => $project->id,
                'old_values' => null,
                'new_values' => $project->toArray(),
                'ip_address' => null,
                'user_agent' => null,
                'created_at' => $project->created_at,
                'updated_at' => $project->created_at,
            ]);
        });
    }

    /**
     * Reverse – we do not delete backfill entries (audit delete is disabled for this table).
     */
    public function down(): void
    {
        // No-op: we do not delete audit history
    }
};
