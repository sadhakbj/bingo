<?php

declare(strict_types=1);

namespace Config;

/**
 * Your SQLite connection config.
 *
 * All env vars are wired in the parent class — this file exists so you
 * can override behaviour without touching the framework.
 */
class SQLiteConfig extends \Core\Config\Driver\SQLiteConfig {}
