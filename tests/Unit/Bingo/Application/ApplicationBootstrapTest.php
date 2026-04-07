<?php

declare(strict_types=1);

namespace Tests\Unit\Bingo\Application;

use App\DTOs\CreateUserDTO;
use App\Models\User;
use App\Repositories\IUserRepository;
use Bingo\Application;
use Bingo\Http\Request;
use Bingo\RateLimit\Contracts\RateLimiterStore;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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

    public function test_boot_is_idempotent(): void
    {
        $app = Application::create(dirname(__DIR__, 4));

        $this->assertSame($app, $app->boot());
        $this->assertSame($app, $app->boot());
    }

    public function test_structural_registration_is_blocked_after_boot(): void
    {
        $app = Application::create(dirname(__DIR__, 4));
        $app->boot();

        $this->expectException(LogicException::class);

        $app->bind(\stdClass::class);
    }

    public function test_handle_auto_boots_application(): void
    {
        $app = Application::create(dirname(__DIR__, 4));

        $response = $app->handle(Request::create('/missing-route', 'GET'));

        $this->assertSame(404, $response->getStatusCode());
    }

    public function test_application_owns_core_path_resolution(): void
    {
        $basePath = '/tmp/bingo-kernel-test';
        $app = Application::create($basePath);

        $this->assertSame('/tmp/bingo-kernel-test', $app->basePath());
        $this->assertSame('/tmp/bingo-kernel-test/app', $app->appPath());
        $this->assertSame('/tmp/bingo-kernel-test/bootstrap', $app->bootstrapPath());
        $this->assertSame('/tmp/bingo-kernel-test/config', $app->configPath());
        $this->assertSame('/tmp/bingo-kernel-test/public', $app->publicPath());
        $this->assertSame('/tmp/bingo-kernel-test/storage', $app->storagePath());
        $this->assertSame('/tmp/bingo-kernel-test/storage/framework/discovery', $app->frameworkPath('discovery'));
    }

    public function test_explicit_logger_instance_is_not_overwritten_during_boot(): void
    {
        $app = Application::create(dirname(__DIR__, 4));
        $logger = new NullLogger();

        $app->instance(LoggerInterface::class, $logger);
        $resolved = $app->make(LoggerInterface::class);

        $this->assertSame($logger, $resolved);
    }

    public function test_explicit_binding_wins_over_discovered_binding(): void
    {
        $app = Application::create(dirname(__DIR__, 4));
        $app->bind(IUserRepository::class, TestUserRepositoryOverride::class);

        $resolved = $app->make(IUserRepository::class);

        $this->assertInstanceOf(TestUserRepositoryOverride::class, $resolved);
    }

    public function test_explicit_rate_limit_store_is_not_overwritten_during_boot(): void
    {
        $app = Application::create(dirname(__DIR__, 4));
        $store = new class implements RateLimiterStore {
            public function increment(string $key, int $windowId, int $decaySeconds): int
            {
                return 1;
            }

            public function count(string $key, int $windowId): int
            {
                return 0;
            }

            public function reset(string $key): void
            {
            }
        };

        $app->instance(RateLimiterStore::class, $store);
        $resolved = $app->make(RateLimiterStore::class);

        $this->assertSame($store, $resolved);
    }
}

final class TestUserRepositoryOverride implements IUserRepository
{
    public function findById(int $id): ?User
    {
        return null;
    }

    public function all(): iterable
    {
        return [];
    }

    public function exists(string $key, string $value): bool
    {
        return false;
    }

    public function create(CreateUserDTO $dto)
    {
        throw new \RuntimeException('Not implemented in test override');
    }
}
