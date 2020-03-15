<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /**
     * @var mixed[]
     */
    protected $guarded = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function roles(): BelongsToMany
    {
        return $this
            ->belongsToMany(Role::class)
            ->withPivot(['meta']);
    }

    public function rolesPivot(): HasMany
    {
        return $this->hasMany(RoleUserPivot::class, 'user_id');
    }

    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function scopeCompanyName(Builder $query, array $args): Builder
    {
        return $query->whereHas('company', function (Builder $q) use ($args): void {
            $q->where('name', $args['company']);
        });
    }

    public function getCompanyNameAttribute()
    {
        return $this->company->name;
    }

    public function scopeNamed(Builder $query): Builder
    {
        return $query->whereNotNull('name');
    }

    public function tasksLoaded(): bool
    {
        return $this->relationLoaded('tasks');
    }

    public function postsCommentsLoaded(): bool
    {
        return $this->relationLoaded('posts')
            && $this
                ->posts
                ->first()
                ->relationLoaded('comments');
    }
}
