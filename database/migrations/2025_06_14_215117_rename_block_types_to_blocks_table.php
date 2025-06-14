<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB; // Required for DB::getDriverName()
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
            // Drop foreign key constraint if it exists
            // The name 'pass_preferences_block_id_foreign' is a common convention
            if (DB::getDriverName() !== 'sqlite') { // SQLite does not support dropping foreign keys this way directly
                $foreignKeys = Schema::getConnection()->getDoctrineSchemaManager()->listTableForeignKeys('pass_preferences');
                foreach ($foreignKeys as $foreignKey) {
                    if (in_array('block_id', $foreignKey->getLocalColumns())) {
                        $table->dropForeign($foreignKey->getName());
                        break;
                    }
                }
            }
        });

        Schema::rename('block_types', 'blocks');

        Schema::table('pass_preferences', function (Blueprint $table) {
            $table->foreign('block_id')
                  ->references('id')->on('blocks')
                  ->onDelete('cascade'); // Assuming cascade was the original intent
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pass_preferences', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $foreignKeys = Schema::getConnection()->getDoctrineSchemaManager()->listTableForeignKeys('pass_preferences');
                foreach ($foreignKeys as $foreignKey) {
                    if (in_array('block_id', $foreignKey->getLocalColumns()) && $foreignKey->getForeignTableName() === 'blocks') {
                        $table->dropForeign($foreignKey->getName());
                        break;
                    }
                }
            }
        });

        Schema::rename('blocks', 'block_types');

        Schema::table('pass_preferences', function (Blueprint $table) {
            $table->foreign('block_id')
                  ->references('id')->on('block_types')
                  ->onDelete('cascade'); // Assuming cascade was the original intent
        });
    }
};
