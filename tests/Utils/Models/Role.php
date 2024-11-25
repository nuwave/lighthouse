<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property string $name
 *
 * Foreign keys
 * @property int|null $acl_id
 *
 * Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\User> $users
 * @property-read \Tests\Utils\Models\ACL|null $acl
 */
final class Role extends Model
{
    public $timestamps = false;

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\Tests\Utils\Models\User, $this> */
    public function users(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class)
            ->withPivot('meta');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\ACL, $this> */
    public function acl(): BelongsTo
    {
        return $this->belongsTo(ACL::class);
    }
}
