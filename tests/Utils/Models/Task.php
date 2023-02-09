<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        static::addGlobalScope('no_cleaning', function (EloquentBuilder $builder): void {
            $builder->where('name', '!=', self::CLEANING);
        });
    }

    public function activity(): MorphMany
    {
        return $this->morphMany(Activity::class, 'content');
    }

    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    public function post(): HasOne
    {
        return $this->hasOne(Post::class);
    }

    public function postComments(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Post::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCompleted(EloquentBuilder $query): EloquentBuilder
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * @param  array<string, int>  $args
     */
    public function scopeFoo(EloquentBuilder $query, array $args): EloquentBuilder
    {
        return $query->limit($args['foo']);
    }

    /**
     * @param  iterable<string>  $tags
     */
    public function scopeWhereTags(EloquentBuilder $query, iterable $tags): EloquentBuilder
    {
        return $query->whereHas('tags', function (EloquentBuilder $query) use ($tags) {
            $query->whereIn('name', $tags);
        });
    }

    public function userLoaded(): bool
    {
        return $this->relationLoaded('user');
    }
}
