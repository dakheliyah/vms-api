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
        Schema::create('mumineens', function (Blueprint $table) {
            $table->id();
            $table->string('its_id')->unique();
            $table->string('eits_id')->nullable();
            $table->string('hof_its_id')->nullable();
            $table->string('full_name');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->integer('age')->nullable();
            $table->string('mobile')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();
            
            // Add index to improve query performance
            $table->index('its_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mumineens');
    }
};
