<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Tests\DBTestCase;
use Tests\Integration\Execution\DataLoader\RelationBatchLoaderTest;
use Tests\Utils\Models\User\UserBuilder;

/**
 * Account of a person who uses this application.
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
 * @property Carbon|null $date_of_birth
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
 * Virtual
 * @property-read string|null $company_name
 * @property-read string $laravel_function_property @see \Tests\Integration\Models\PropertyAccessTest
 * @property-read int $expensive_property @see \Tests\Integration\Models\PropertyAccessTest
 *
 * Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Tests\Utils\Models\AlternateConnection> $alternateConnections
 * @property-read \Tests\Utils\Models\Company|null $company
 * @property-read \Tests\Utils\Models\Image|null $image
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Tests\Utils\Models\CustomPrimaryKey> $customPrimaryKeys
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Tests\Utils\Models\Post> $posts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Tests\Utils\Models\Role> $roles
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Tests\Utils\Models\RoleUserPivot> $rolesPivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Tests\Utils\Models\Task> $tasks
 * @property-read \Tests\Utils\Models\Team|null $team
 */
final class User extends Authenticatable
{
    public const INCREMENTING_ATTRIBUTE_VALUE = 'value of the incrementing attribute';

    public const FUNCTION_PROPERTY_ATTRIBUTE_VALUE = 'value of the virtual property';

    public const PHP_PROPERTY_VALUE = 'value of the PHP property';

    /**
     * Ensure that this is functionally equivalent to leaving this as null.
     *
     * @see RelationBatchLoaderTest::testDoesNotBatchloadRelationsWithDifferentDatabaseConnections()
     */
    protected $connection = DBTestCase::DEFAULT_CONNECTION;

    // @phpstan-ignore-next-line iterable type missing in Laravel 9.0.0
    protected $casts = [
        'email_verified_at' => 'datetime',
        'date_of_birth' => 'date',
    ];

    /** @see \Tests\Integration\Models\PropertyAccessTest */
    public string $php_property = self::PHP_PROPERTY_VALUE;

    public function newEloquentBuilder($query): UserBuilder
    {
        return new UserBuilder($query);
    }

    public static function query(): UserBuilder
    {
        return parent::query(); // @phpstan-ignore-line this function is more of an assertion
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\Tests\Utils\Models\AlternateConnection, $this> */
    public function alternateConnections(): HasMany
    {
        return $this->hasMany(AlternateConnection::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\Tests\Utils\Models\CustomPrimaryKey, $this> */
    public function customPrimaryKeys(): HasMany
    {
        return $this->hasMany(CustomPrimaryKey::class, 'user_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphOne<\Tests\Utils\Models\Image, $this> */
    public function image(): MorphOne
    {
        return $this->morphOne(Image::class, 'imageable');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\Tests\Utils\Models\Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Tests\Utils\Models\Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot('meta');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\Tests\Utils\Models\RoleUserPivot, $this> */
    public function rolesPivot(): HasMany
    {
        return $this->hasMany(RoleUserPivot::class, 'user_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\Tests\Utils\Models\Task, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function getCompanyNameAttribute(): ?string
    {
        return $this->company?->name;
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
            && $this->posts->first()
                ?->relationLoaded('comments');
    }

    public function tasksAndPostsCommentsLoaded(): bool
    {
        return $this->tasksLoaded()
            && $this->postsCommentsLoaded();
    }

    public function postsTaskLoaded(): bool
    {
        return $this->relationLoaded('posts')
            && $this->posts->first()
                ?->relationLoaded('task');
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

    /** @see \Tests\Integration\Models\PropertyAccessTest */
    public function getLaravelFunctionPropertyAttribute(): string
    {
        return self::FUNCTION_PROPERTY_ATTRIBUTE_VALUE;
    }

    /** @see \Tests\Integration\Models\PropertyAccessTest */
    public function getExpensivePropertyAttribute(): int
    {
        static $counter = 0;
        ++$counter;

        return $counter;
    }

    /** @see \Tests\Integration\Models\PropertyAccessTest */
    public function getIncrementingAttribute(): string
    {
        return self::INCREMENTING_ATTRIBUTE_VALUE;
    }

    /** @see \Tests\Integration\Models\PropertyAccessTest */
    public function getExistsAttribute(): ?bool // @phpstan-ignore return.unusedType
    {
        return null;
    }
}
