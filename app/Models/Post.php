<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

#[Table(name: 'posts')]
class Post extends Model
{
    public function user() 
     {
        return $this->belongsTo(User::class);
     }
}
