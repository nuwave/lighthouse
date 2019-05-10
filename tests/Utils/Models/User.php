<?php

namespace Tests\Utils\Models;

use BenSampo\Enum\Traits\CastsEnums;
use Tests\Utils\LaravelEnums\UserType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        return $this->belongsToMany(Role::class);
    }

    public function scopeCompanyName(Builder $query, array $args): Builder
    {
        return $query->whereHas('company', function (Builder $q) use ($args): void {
            $q->where('name', $args['company']);
        });
    }
}
