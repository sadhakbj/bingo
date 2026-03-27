<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Framework\Database\Database;

// Initialize Eloquent
Database::setup();

// Include migration files
include_once __DIR__ . '/migrations/2023_01_01_000000_create_users_and_posts_table.php';