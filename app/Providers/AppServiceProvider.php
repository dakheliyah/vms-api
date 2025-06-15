<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Doctrine\DBAL\Types\Type;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (class_exists(Type::class)) {
            // Prevent error: Unknown column type "enum" requested. 
            // See https://github.com/laravel/framework/issues/13461
            // And https://stackoverflow.com/questions/32370000/laravel-5-1-unknown-database-type-enum-requested
            // Check if the mapping already exists to avoid re-registering (optional, but good practice)
            if (!Type::hasType('enum')) {
                Type::addType('enum', \Doctrine\DBAL\Types\StringType::class);
            }
            // For PostgreSQL, or if the above doesn't work alone:
            $platform = DB::connection()->getDoctrineSchemaManager()->getDatabasePlatform();
            if (!$platform->hasDoctrineTypeMappingFor('enum')) {
                $platform->registerDoctrineTypeMapping('enum', 'string');
            }
        }
        //
    }
}
