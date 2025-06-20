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
            $table->unsignedBigInteger('hizbe_saifee_group_id')->nullable()->after('jamaat');
            $table->foreign('hizbe_saifee_group_id')
                  ->references('id')->on('hizbe_saifee_groups')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mumineens', function (Blueprint $table) {
            $table->dropForeign(['hizbe_saifee_group_id']);
            $table->dropColumn('hizbe_saifee_group_id');
        });
    }
};
