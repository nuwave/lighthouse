<?php

namespace Nuwave\Lighthouse\Tests\Utils\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Nuwave\Lighthouse\Support\Traits\HasRelayConnections;

class User extends Authenticatable
{
    use HasRelayConnections;

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
