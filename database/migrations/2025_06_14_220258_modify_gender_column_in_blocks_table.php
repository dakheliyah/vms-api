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
        Schema::table('blocks', function (Blueprint $table) {
            // It's good practice to ensure doctrine/dbal is installed for column modifications
            // $table->string('gender')->nullable()->change(); // Example if it was not nullable
            $table->enum('gender', ['male', 'female', 'both'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            $table->string('gender')->nullable()->change();
        });
    }
};
