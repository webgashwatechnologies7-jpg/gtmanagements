<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('project_type_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
            $table->enum('status', ['planning', 'active', 'on_hold', 'completed', 'cancelled'])->default('planning');
            $table->date('deadline')->nullable();
            $table->decimal('estimated_hours', 10, 2)->nullable();
            $table->decimal('actual_hours', 10, 2)->default(0);
            $table->foreignId('project_manager_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('team_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('created_by')->constrained('users');
            $table->date('start_date')->nullable();
            $table->date('completion_date')->nullable();
            $table->timestamps();
            
            $table->index('status');
            $table->index('priority');
            $table->index('project_manager_id');
            $table->index('team_id');
            $table->index('deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
