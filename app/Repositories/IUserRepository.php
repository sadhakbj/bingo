<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CreateUserDTO;
use App\Models\User;
use Bingo\Attributes\Provider\Bind;

#[Bind(UserRepository::class)]
interface IUserRepository
{
    public function findById(int $id): ?User;

    public function all(): iterable;

    public function exists(string $key, string $value): bool;

    public function create(CreateUserDTO $dto);
}
