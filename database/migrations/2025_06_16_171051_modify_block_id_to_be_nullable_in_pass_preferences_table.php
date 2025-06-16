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
            // Foreign key constraints must be dropped before modifying the column.
            $table->dropForeign(['block_id']);
            // Now, modify the column to be nullable.
            $table->foreignId('block_id')->nullable()->change();
            // Finally, re-add the foreign key constraint.
            $table->foreign('block_id')->references('id')->on('blocks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pass_preferences', function (Blueprint $table) {
            // To reverse, we do the same: drop foreign key, change column, re-add key.
            $table->dropForeign(['block_id']);
            // Change the column back to being non-nullable.
            $table->foreignId('block_id')->nullable(false)->change();
            // Re-add the foreign key, perhaps with cascade on delete as it was before.
            $table->foreign('block_id')->references('id')->on('blocks')->onDelete('cascade');
        });
    }
};
