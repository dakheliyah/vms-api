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
            // Rename columns
            $table->renameColumn('hof_its_id', 'hof_id');
            $table->renameColumn('full_name', 'fullname');

            // Drop column
            if (Schema::hasColumn('mumineens', 'eits_id')) {
                $table->dropColumn('eits_id');
            }

            // Add new columns - after existing columns like 'age', 'mobile', 'country'
            // The specific order of new columns can be after 'country' or 'timestamps'
            // For simplicity, adding them here. Their actual position can be fine-tuned if needed.
            $table->string('jamaat')->nullable()->after('country');
            $table->string('idara')->nullable()->after('jamaat');
            $table->string('category')->nullable()->after('idara');
            $table->string('prefix')->nullable()->after('category');
            $table->string('title')->nullable()->after('prefix');
            $table->string('venue_waaz')->nullable()->after('title');
            $table->string('city')->nullable()->after('venue_waaz');
            $table->boolean('local_mehman')->nullable()->default(false)->after('city');
            $table->string('arr_place_date')->nullable()->after('local_mehman'); // Storing as string for flexibility
            $table->string('flight_code')->nullable()->after('arr_place_date');
            $table->boolean('whatsapp_link_clicked')->nullable()->default(false)->after('flight_code');
            $table->boolean('daily_trans')->nullable()->default(false)->after('whatsapp_link_clicked');
            $table->string('acc_arranged_at')->nullable()->after('daily_trans');
            $table->string('acc_zone')->nullable()->after('acc_arranged_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mumineens', function (Blueprint $table) {
            // Drop new columns
            $table->dropColumn([
                'jamaat',
                'idara',
                'category',
                'prefix',
                'title',
                'venue_waaz',
                'city',
                'local_mehman',
                'arr_place_date',
                'flight_code',
                'whatsapp_link_clicked',
                'daily_trans',
                'acc_arranged_at',
                'acc_zone',
            ]);

            // Add back dropped column
            $table->integer('eits_id')->nullable()->after('its_id'); // Assuming original position

            // Rename columns back
            $table->renameColumn('fullname', 'full_name');
            $table->renameColumn('hof_id', 'hof_its_id');
        });
    }
};
