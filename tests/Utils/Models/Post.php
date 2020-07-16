<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property string $title
 * @property string|null $body
 * @property int|null $user_id
 * @property int $task_id
 * @property int|null $parent_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @property-read \Tests\Utils\Models\Activity $activity
 */
class Post extends Model
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
}
