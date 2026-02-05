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
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'holiday', 'leave', 'half_day'])->default('absent');
            $table->timestamp('check_in_time')->nullable();
            $table->timestamp('check_out_time')->nullable();
            $table->integer('break_minutes')->default(60)->comment('Break time in minutes');
            $table->integer('total_work_minutes')->default(0);
            $table->integer('regular_minutes')->default(0)->comment('Max 480 (8 hours)');
            $table->integer('overtime_minutes')->default(0);
            $table->foreignId('marked_by')->nullable()->constrained('users')->onDelete('set null')->comment('If manually marked by TL/Admin');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['user_id', 'date']);
            $table->index('user_id');
            $table->index('date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
