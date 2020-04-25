<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 */
class Team extends Model
{
    protected $guarded = [];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
