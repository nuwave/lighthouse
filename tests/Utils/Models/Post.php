<?php


namespace Tests\Utils\Models;


use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    public function comments() {
        return $this->hasMany(Comment::class);
    }
}