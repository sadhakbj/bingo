<?php

declare(strict_types=1);

namespace App\Repositories;

use App\DTOs\CreateUserDTO;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements IUserRepository
{
    public function all(): iterable
    {
        return User::all();
    }

    public function create(CreateUserDTO $dto)
    {
        $data = $dto->toArray();

        if (isset($data['password'])) {
            $data['password'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }

        return User::query()->create($data);
    }

    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function exists(string $key, string $value): bool
    {
        return User::query()->where($key, $value)->exists();
    }
}
