# Eloquent ORM

Bingo supports Laravel Eloquent models without additional setup.

## Model example

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email', 'age', 'bio'];
    protected $hidden = ['password'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

## Migrations

Migrations are plain PHP files stored in `database/migrations` and are executed in alphabetical order.

```php
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

if (!Capsule::schema()->hasTable('posts')) {
    Capsule::schema()->create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content');
        $table->foreignId('user_id')->constrained('users');
        $table->timestamps();
    });
}
```

## Generator

```bash
php bin/bingo generate:model Post
```
