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
        Schema::create('eod_report_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('eod_report_id');
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('task_id')->nullable();
            $table->text('work_summary');
            $table->integer('progress_percentage')->default(0)->comment('0-100');
            $table->text('remaining_work')->nullable();
            $table->text('blockers')->nullable();
            $table->integer('regular_minutes')->default(0);
            $table->integer('overtime_minutes')->default(0);
            $table->string('status_update', 50)->nullable();
            $table->timestamps();

            $table->foreign('eod_report_id')->references('id')->on('eod_reports')->onDelete('cascade');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            // task_id FK added in 2026_02_03_120107 (tasks table created later)

            $table->index('eod_report_id');
            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eod_report_items');
    }
};
