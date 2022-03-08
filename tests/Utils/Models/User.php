<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tests\DBTestCase;
use Tests\Integration\Execution\DataLoader\RelationBatchLoaderTest;

/**
 * Account of a person who utilizes this application.
 *
 * Primary key
 *
 * @property int $id
 *
 * Attributes
 * @property string|null $name
 * @property string|null $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $remember_token
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Foreign keys
 * @property int|null $company_id
 * @property int|null $team_id
 * @property int|null $person_id
 * @property string|null $person_type
 *
 * Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\AlternateConnection> $alternateConnections
 * @property-read \Tests\Utils\Models\Company|null $company
 * @property-read \Tests\Utils\Models\Image|null $image
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Post> $posts
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Role> $roles
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\RoleUserPivot> $rolesPivot
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Task> $tasks
 * @property-read \Tests\Utils\Models\Team|null $team
 */
class User extends Authenticatable
{
    /**
     * Ensure that this is functionally equivalent to leaving this as null.
     *
     * @see RelationBatchLoaderTest::testDoesNotBatchloadRelationsWithDifferentDatabaseConnections()
     */
    protected $connection = DBTestCase::DEFAULT_CONNECTION;

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function alternateConnections(): HasMany
    {
        return $this->hasMany(AlternateConnection::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function roles(): BelongsToMany
    {
        return $this
            ->belongsToMany(Role::class)
            ->withPivot('meta');
    }

    public function rolesPivot(): HasMany
    {
        return $this->hasMany(RoleUserPivot::class, 'user_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function scopeCompanyName(Builder $query, array $args): Builder
    {
        return $query->whereHas('company', function (Builder $q) use ($args): void {
            $q->where('name', $args['company']);
        });
    }

    public function scopeNamed(Builder $query): Builder
    {
        return $query->whereNotNull('name');
    }

    public function getCompanyNameAttribute(): string
    {
        return $this->company->name;
    }

    public function tasksLoaded(): bool
    {
        return $this->relationLoaded('tasks');
    }

    public function tasksCountLoaded(): bool
    {
        return isset($this->attributes['tasks_count']);
    }

    public function postsCommentsLoaded(): bool
    {
        return $this->relationLoaded('posts')
            && $this
                ->posts
                ->first()
                ->relationLoaded('comments');
    }

    public function tasksAndPostsCommentsLoaded(): bool
    {
        return $this->tasksLoaded()
            && $this->postsCommentsLoaded();
    }

    public function postsTaskLoaded(): bool
    {
        return $this->relationLoaded('posts')
            && $this
                ->posts
                ->first()
                ->relationLoaded('task');
    }

    public function postTasksAndPostsCommentsLoaded(): bool
    {
        return $this->postsTaskLoaded()
            && $this->postsCommentsLoaded();
    }

    public function nonRelationPrimitive(): string
    {
        return 'foo';
    }
}
