<?php


namespace Tests\Utils\Models;


use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Post extends Model
{
    use Searchable;

    protected $visible = ['id'];

    public function comments() {
        return $this->hasMany(Comment::class);
    }
}
