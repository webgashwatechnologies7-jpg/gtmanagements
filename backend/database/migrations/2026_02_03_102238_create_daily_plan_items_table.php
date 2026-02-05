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
        Schema::create('daily_plan_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('daily_plan_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->decimal('planned_hours', 5, 2);
            $table->text('description')->nullable();
            $table->enum('priority', ['high', 'medium', 'low'])->default('medium');
            $table->timestamps();

            $table->foreign('daily_plan_id')->references('id')->on('daily_plans')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            // task_id FK added in later migration (tasks table is created after this)

            $table->index('daily_plan_id');
            $table->index('project_id');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_plan_items');
    }
};
