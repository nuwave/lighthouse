<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property string|null $meta
 *
 * Foreign keys
 * @property int $user_id
 * @property int $role_id
 *
 * Relations
 * @property-read \Tests\Utils\Models\Role $role
 * @property-read \Tests\Utils\Models\User $user
 */
final class RoleUserPivot extends Model
{
    public $table = 'role_user';

    public $timestamps = false;

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
