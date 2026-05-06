<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\CreateUserDTO;
use App\DTOs\User\UserDTO;
use App\Repositories\IUserRepository;
use Bingo\Exceptions\Http\ConflictException;
use Bingo\Exceptions\Http\NotFoundException;
use Psr\Log\LoggerInterface;

readonly class UserService
{
    public function __construct(
        private LoggerInterface $logger,
        private IUserRepository $userRepo,
    ) {}

    public function createUser(CreateUserDTO $dto): UserDTO
    {
        $this->logger->debug('Creating user', ['email' => $dto->email]);

        if ($this->userRepo->exists('email', $dto->email)) {
            $this->logger->warning('Duplicate email on user creation', ['email' => $dto->email]);
            throw new ConflictException('Email already exists');
        }

        $user = $this->userRepo->create($dto);

        $this->logger->info('User created', ['id' => $user->id, 'email' => $user->email]);

        return UserDTO::fromModel($user->loadMissing('posts'));
    }

    public function getUserById(int $id): UserDTO
    {
        $this->logger->debug('Fetching user', ['id' => $id]);

        $user = $this->userRepo->findById($id);

        if (!$user) {
            $this->logger->info('User not found', ['id' => $id]);
            throw new NotFoundException('User not found');
        }

        return UserDTO::fromModel($user);
    }

    /**
     * Demo SSE payloads: "User 1" … "User 10".
     *
     * @return \Generator<int, array{index: int, label: string}>
     */
    public function demoUserStreamChunks(): \Generator
    {
        for ($i = 1; $i <= 10; $i++) {
            sleep(1);
            yield [
                'index' => $i,
                'label' => "User {$i}",
            ];
        }
    }
}
