<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property string $name
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\User> $users
 */
final class Team extends Authenticatable
{
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\Tests\Utils\Models\User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
