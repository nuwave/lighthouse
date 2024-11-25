<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property string $name
 * @property string $default_string
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Post> $posts
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Task> $tasks
 */
final class Tag extends Model
{
    /** @return \Illuminate\Database\Eloquent\Relations\MorphToMany<\Tests\Utils\Models\Post, $this> */
    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'taggable');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphToMany<\Tests\Utils\Models\Task, $this> */
    public function tasks(): MorphToMany
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }
}
