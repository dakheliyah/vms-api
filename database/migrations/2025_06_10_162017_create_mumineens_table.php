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
            // Using its_id as primary key with integer data type for 8-digit number
            $table->integer('its_id')->primary();
            $table->integer('eits_id')->nullable();
            $table->integer('hof_its_id')->nullable();
            $table->string('full_name');
            $table->enum('gender', ['male', 'female', 'other']);
            $table->integer('age')->nullable();
            $table->string('mobile')->nullable();
            $table->string('country')->nullable();
            $table->timestamps();
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
