<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property string $name
 * @property int|null $difficulty
 * @property string|null $guard
 * @property \Illuminate\Support\Carbon $completed_at
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Foreign keys
 * @property int|null $user_id
 *
 * Relations
 * @property-read \Tests\Utils\Models\Activity $activity
 * @property-read \Tests\Utils\Models\Image $image
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Image> $images
 * @property-read \Tests\Utils\Models\Post|null $post
 * @property-read \Tests\Utils\Models\Comment|null $postComments
 * @property-read \Tests\Utils\Models\PostStatus|null $postStatus
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Tag> $tags
 * @property-read \Tests\Utils\Models\User|null $user
 */
final class Task extends Model
{
    use SoftDeletes;

    public const CLEANING = 'cleaning';

    protected static function boot(): void
    {
        parent::boot();

        // This is used to test that this scope works in all kinds of queries
        static::addGlobalScope('no_cleaning', static function (EloquentBuilder $builder): void {
            $builder->where('name', '!=', self::CLEANING);
        });
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany<\Tests\Utils\Models\Activity> */
    public function activity(): MorphMany
    {
        return $this->morphMany(Activity::class, 'content');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphOne<\Tests\Utils\Models\Image> */
    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany<\Tests\Utils\Models\Image> */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<\Tests\Utils\Models\Post> */
    public function post(): HasOne
    {
        return $this->hasOne(Post::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasManyThrough<\Tests\Utils\Models\Comment> */
    public function postComments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Post::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOneThrough<\Tests\Utils\Models\PostStatus> */
    public function postStatus(): HasOneThrough
    {
        return $this->hasOneThrough(PostStatus::class, Post::class, 'task_id', 'post_id');
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

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     *
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeCompleted(EloquentBuilder $query): EloquentBuilder
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @param  array<string, int>  $args
     *
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeFoo(EloquentBuilder $query, array $args): EloquentBuilder
    {
        return $query->limit($args['foo']);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @param  iterable<string>  $tags
     *
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeWhereTags(EloquentBuilder $query, iterable $tags): EloquentBuilder
    {
        return $query->whereHas('tags', static fn (EloquentBuilder $query): EloquentBuilder => $query->whereIn('name', $tags));
    }

    public function userLoaded(): bool
    {
        return $this->relationLoaded('user');
    }
}
