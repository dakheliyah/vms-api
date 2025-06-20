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
        Schema::table('pass_preferences', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('block_id'); // Add is_locked field
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pass_preferences', function (Blueprint $table) {
            $table->dropColumn('is_locked'); // Revert the change
        });
    }
};
