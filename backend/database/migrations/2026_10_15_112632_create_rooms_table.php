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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('capacity');
            $table->text('description')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('image_url')->nullable();
            $table->string('color_code', 7)->nullable();
            $table->string('location_type', 16)->default('indoor');
            $table->string('lighting', 16)->default('none');
            $table->unsignedInteger('size_sqm')->nullable();
            $table->decimal('width_meters', 5, 2)->nullable();
            $table->decimal('length_meters', 5, 2)->nullable();
            $table->decimal('height_meters', 5, 2)->nullable();
            $table->timestamps();
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
