<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\Application;

use Bingo\Application;
use PHPUnit\Framework\TestCase;

class ApplicationBootstrapTest extends TestCase
{
    public function test_create_does_not_require_dot_env_file_when_process_env_is_available(): void
    {
        $basePath = sys_get_temp_dir() . '/bingo-no-env-' . bin2hex(random_bytes(8));
        mkdir($basePath, 0755, true);

        try {
            $app = Application::create($basePath);

            $this->assertInstanceOf(Application::class, $app);
            $this->assertSame($basePath, $app->basePath);
        } finally {
            @rmdir($basePath);
        }
    }
}
