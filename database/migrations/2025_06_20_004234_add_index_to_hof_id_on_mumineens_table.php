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
        Schema::table('mumineens', function (Blueprint $table) {
            $table->index('hof_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mumineens', function (Blueprint $table) {
            $table->dropIndex(['hof_id']); // Laravel defaults to naming the index <table_name>_<column_name>_index
        });
    }
};
