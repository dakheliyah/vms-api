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
        Schema::create('accommodations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('miqaat_id'); // Assuming miqaat_id refers to a miqaats table
            $table->unsignedBigInteger('its_id'); // Assuming its_id refers to a mumineen table or similar
            $table->string('type'); // e.g., 'Hotel', 'Apartment', 'Guest House'
            $table->string('name'); // e.g., 'Hilton Colombo', 'My Home'
            $table->text('address');
            $table->timestamps();

            // Optional: Add indexes for frequently queried columns
            $table->index('miqaat_id');
            $table->index('its_id');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accommodations');
    }
};
