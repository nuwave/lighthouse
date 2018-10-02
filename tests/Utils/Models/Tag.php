<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    protected $guarded = [];

    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    public function tasks(): MorphToMany
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }
}
