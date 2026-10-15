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
        Schema::create('course_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_session_id')->constrained('course_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->nullOnDelete(); // nullable: Autor kann Admin/Trainer sein
            $table->string('type', 32); // 'note' | 'cancelled' | 'substitute'
            $table->text('body');
            $table->timestamps();
            $table->index('course_session_id');
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
