# Eloquent ORM

Bingo integrates [Laravel Eloquent](https://laravel.com/docs/eloquent) via `illuminate/database`. Models, relationships, query scopes, and migrations all work exactly as they do in Laravel.

---

## Defining a Model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email', 'age', 'bio'];
    protected $hidden   = ['password'];
    protected $casts    = ['age' => 'integer', 'created_at' => 'datetime'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}
```

---

## Common Query Examples

```php
// All records
$users = User::all();

// Single record
$user = User::find($id);                   // returns null if not found
$user = User::findOrFail($id);             // throws ModelNotFoundException

// Where conditions
$users = User::where('age', '>=', 18)->get();
$user  = User::where('email', $email)->first();

// Create
$user = User::create(['name' => 'Alice', 'email' => 'alice@example.com']);

// Update
$user->update(['name' => 'Alice Smith']);

// Delete
$user->delete();

// Pagination
$users = User::paginate(20);

// With relationships
$users = User::with('posts', 'profile')->get();

// Counting
$total = User::count();
$active = User::where('active', true)->count();
```

---

## Relationships

```php
// hasMany — a User has many Posts
public function posts()
{
    return $this->hasMany(Post::class);
}

// belongsTo — a Post belongs to a User
public function user()
{
    return $this->belongsTo(User::class);
}

// hasOne — a User has one Profile
public function profile()
{
    return $this->hasOne(Profile::class);
}

// belongsToMany — many-to-many
public function roles()
{
    return $this->belongsToMany(Role::class);
}
```

Access relationships:

```php
$user->posts;           // Collection of Post models
$user->posts()->where('published', true)->get();  // Constrained query
$post->user->name;      // Parent model
```

---

## Generating a Model

```bash
php bin/bingo g:model Post
```

Creates `app/Models/Post.php` with sensible defaults.

---

## Migrations

Migration files live in `database/migrations/` and are executed in alphabetical order. Name them with a datestamp prefix to control order:

```
database/migrations/
  2024_01_01_000001_create_users_table.php
  2024_01_01_000002_create_posts_table.php
```

### Writing a Migration

```php
<?php

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

// Create table (idempotent)
if (!Capsule::schema()->hasTable('posts')) {
    Capsule::schema()->create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content');
        $table->boolean('published')->default(false);
        $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
        $table->timestamps();
    });
}
```

### Running Migrations

```bash
php bin/bingo db:migrate
```

### Generating a Migration

```bash
php bin/bingo g:migration create_posts_table
```

Creates a stub in `database/migrations/` ready to edit.

---

## Multiple Database Connections

Switch the active connection on a per-query basis:

```php
// Named connection
$users = User::on('pgsql')->get();

// Raw query on a connection
$results = \Illuminate\Database\Capsule\Manager::connection('mysql')
    ->table('orders')
    ->where('status', 'pending')
    ->get();
```

---

## Read Replicas

When `DB_READ_HOST` is set, Bingo automatically enables read/write splitting. Reads (`SELECT`) go to the replica; writes go to the primary.

For multiple replicas, override `toArray()` in your driver config class:

```php
// config/MySqlConfig.php
class MySqlConfig extends \Bingo\Config\Driver\MySqlConfig
{
    public function toArray(): array
    {
        $config = parent::toArray();
        $config['read']['host'] = [
            env('DB_READ_HOST_1', '10.0.0.2'),
            env('DB_READ_HOST_2', '10.0.0.3'),
        ];
        return $config;
    }
}
```

---

## Query Scopes

```php
class Post extends Model
{
    public function scopePublished($query)
    {
        return $query->where('published', true);
    }

    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}

// Usage
$posts = Post::published()->byUser($userId)->get();
```

---

## Soft Deletes

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;
}

$post->delete();           // sets deleted_at
Post::withTrashed()->get(); // includes soft-deleted
$post->restore();           // undeletes
$post->forceDelete();       // permanent delete
```
