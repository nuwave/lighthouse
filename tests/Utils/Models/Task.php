<?php

namespace Nuwave\Lighthouse\Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Traits\HasRelayConnections;

class Task extends Model
{
    use HasRelayConnections;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
