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
        Schema::create('block_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vaaz_center_id')->constrained()->onDelete('cascade');
            $table->string('type');
            $table->integer('capacity');
            $table->integer('min_age')->nullable();
            $table->integer('max_age')->nullable();
            $table->string('gender')->nullable(); // e.g., 'Male', 'Female', 'All'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('block_types');
    }
};
