<?php

namespace Tests\Utils\Models;

use BenSampo\Enum\Traits\CastsEnums;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;
use Tests\Utils\LaravelEnums\UserType;

class User extends Authenticatable
{
    use CastsEnums;

    /**
     * @var mixed[]
     */
    protected $guarded = [];

    protected $enumCasts = [
        'type' => UserType::class,
    ];

    public function getTaskCountAsString(): string
    {
        if (! $this->relationLoaded('tasks')) {
            return 'This relation should have been preloaded via @with';
        }

        return "User has {$this->tasks->count()} tasks.";
    }

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
}
