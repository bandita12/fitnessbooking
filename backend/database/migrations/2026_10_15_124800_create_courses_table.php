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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('image_url')->nullable();
            $table->string('web_link')->nullable();
            $table->unsignedTinyInteger('day_of_week'); // 1..7 = Mo..So
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('capacity')->nullable(); // null = inherit from room
            $table->timestamps();
            $table->index('studio_id');
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
