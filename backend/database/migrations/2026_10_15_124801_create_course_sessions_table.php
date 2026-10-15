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
        Schema::create('course_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('date');
            $table->string('status', 16)->default('scheduled'); // 'scheduled'|'cancelled'|'substituted'
            $table->text('session_info')->nullable();
            $table->unsignedInteger('capacity')->nullable(); // null = inherit from course
            $table->timestamps();
            $table->index(['course_id', 'date']);
            $table->index('trainer_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Intentionally empty: deployed migrations are immutable and rollbacks
     * are never executed in this project.
     */
    public function down(): void
    {
        //
    }
};
