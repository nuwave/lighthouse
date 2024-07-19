<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
 *
 * Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Activity> $activity
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Category> $categories
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Post> $children
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Comment> $comments
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Image> $images
 * @property-read \Tests\Utils\Models\Post|null $parent
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\RoleUserPivot> $roles
 * @property-read \Tests\Utils\Models\PostStatus|null $status
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Tag> $tags
 * @property-read \Tests\Utils\Models\Task $task
 * @property-read \Tests\Utils\Models\User|null $user
 */
final class Post extends Model
{
    use Searchable;
    use SoftDeletes;

    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany<\Tests\Utils\Models\Activity> */
    public function activity(): MorphMany
    {
        return $this->morphMany(Activity::class, 'content');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Tests\Utils\Models\Category> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_post', 'category_id', 'post_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<self> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\Tests\Utils\Models\Comment> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany<\Tests\Utils\Models\Image> */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<self, self> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\Tests\Utils\Models\RoleUserPivot> */
    public function roles(): HasMany
    {
        return $this->hasMany(RoleUserPivot::class, 'user_id', 'user_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<\Tests\Utils\Models\PostStatus> */
    public function status(): HasOne
    {
        return $this->hasOne(PostStatus::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\Task, self> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphToMany<\Tests\Utils\Models\Tag> */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
