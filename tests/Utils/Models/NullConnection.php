<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\User> $users
 */
final class NullConnection extends Model
{
    public $timestamps = false;

    public function getConnectionName(): ?string
    {
        return null;
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\Tests\Utils\Models\User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }
}
