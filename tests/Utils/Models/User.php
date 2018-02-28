<?php

namespace Nuwave\Lighthouse\Tests\Utils\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Nuwave\Lighthouse\Support\Traits\IsRelayConnection;

class User extends Authenticatable
{
    use IsRelayConnection;

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }
}
