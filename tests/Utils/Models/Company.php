<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
final class Company extends Model
{
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
