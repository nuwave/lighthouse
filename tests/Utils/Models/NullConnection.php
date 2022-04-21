<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Tests\DBTestCase;

use function var_dump;

/**
 * Primary key.
 * @property int $id
 *
 * Relations
 * @property-read \Tests\Utils\Models\User|null $users
 */
final class NullConnection extends Model
{
    public $timestamps = false;

    public function getConnectionName()
    {
        return null;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'company_id');
    }
}
