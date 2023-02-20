<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property string $title
 * @property string|null $body
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Foreign keys
 * @property int|null $user_id
 * @property int $task_id
 * @property int|null $parent_id
 * @property-read \Tests\Utils\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Activity> $activity
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Comment> $comments
 * @property-read \Tests\Utils\Models\Task $task
 * @property-read \Tests\Utils\Models\Post $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Post> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Tag> $tags
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Image> $images
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\RoleUserPivot> $roles
 */
final class Post extends Model
{
    use Searchable;
    use SoftDeletes;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function activity(): MorphMany
    {
        return $this->morphMany(Activity::class, 'content');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_post', 'category_id', 'post_id');
    }

    public function roles(): HasMany
    {
        return $this->hasMany(RoleUserPivot::class, 'user_id', 'user_id');
    }
}
