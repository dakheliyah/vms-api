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
        Schema::table('vaaz_centers', function (Blueprint $table) {
            $table->integer('male_capacity')->nullable()->after('est_capacity');
            $table->integer('female_capacity')->nullable()->after('male_capacity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vaaz_centers', function (Blueprint $table) {
            $table->dropColumn(['male_capacity', 'female_capacity']);
        });
    }
};
