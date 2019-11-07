<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    protected static function boot()
    {
        parent::boot();

        // This is used to test that this scope works in all kinds of queries
        static::addGlobalScope('no_cleaning', function (Builder $builder): void {
            $builder->where('name', '!=', 'cleaning');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function post(): HasOne
    {
        return $this->hasOne(Post::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function scopeFoo(Builder $query, $args): Builder
    {
        return $query->limit($args['foo']);
    }

    public function hours(): MorphMany
    {
        return $this->morphMany(Hour::class, 'hourable');
    }

    public function hour(): MorphOne
    {
        return $this->morphOne(Hour::class, 'hourable');
    }

    public function scopeWhereTags(Builder $query, $tags)
    {
        return $query->whereHas('tags', function (Builder $query) use ($tags) {
            $query->whereIn('name', $tags);
        });
    }
}
