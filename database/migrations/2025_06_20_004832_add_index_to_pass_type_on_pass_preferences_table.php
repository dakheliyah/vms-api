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
            $table->index('pass_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pass_preferences', function (Blueprint $table) {
            $table->dropIndex(['pass_type']); // Laravel defaults to naming the index <table_name>_<column_name>_index
        });
    }
};
