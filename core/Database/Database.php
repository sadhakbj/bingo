<?php

namespace Core\Database;

use Illuminate\Database\Capsule\Manager as Capsule;

class Database
{
    protected static $instance = null;

    public static function setup()
    {
        if (self::$instance === null) {
            self::$instance = new Capsule;

            // Configure database connection (using SQLite for simplicity)
            self::$instance->addConnection([
                'driver' => 'sqlite',
                'database' => __DIR__ . '/../../database/database.sqlite',
            ]);

            // Make this Capsule instance available globally via static methods
            self::$instance->setAsGlobal();

            // Setup the Eloquent ORM
            self::$instance->bootEloquent();
        }

        return self::$instance;
    }
}
