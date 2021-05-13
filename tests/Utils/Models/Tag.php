<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Primary key.
 * @property int $id
 *
 * Attributes
 * @property string $name
 * @property string $default_string
 *
 * Timestamps
 * @property \lluminate\Support\Carbon $created_at
 * @property \lluminate\Support\Carbon $updated_at
 *
 * Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Post> $posts
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Task> $tasks
 */
class Tag extends Model
{
    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    public function tasks(): MorphToMany
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }
}
