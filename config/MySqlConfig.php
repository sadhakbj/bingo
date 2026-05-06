<?php

declare(strict_types = 1);

namespace Config;

/**
 * Your MySQL connection config.
 *
 * All env vars are wired in the parent class — this file exists so you
 * can override behaviour without touching the framework.
 *
 * Examples:
 *
 *   // Multiple read replicas — override toArray()
 *   public function toArray(): array {
 *       $config = parent::toArray();
 *       $config['read']['host'] = [
 *           env('DB_READ_HOST_1', '192.168.1.1'),
 *           env('DB_READ_HOST_2', '192.168.1.2'),
 *       ];
 *       return $config;
 *   }
 */
class MySqlConfig extends \Bingo\Config\Driver\MySqlConfig
{
}
