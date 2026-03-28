<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\{Fillable, Hidden, Table};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table(name: 'users')]
#[Fillable(columns: ['name', 'email', 'password', 'age', 'bio'])]
#[Hidden(columns: ['password'])]
class User extends Model
{
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
