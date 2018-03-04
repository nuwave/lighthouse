<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Traits\IsRelayConnection;

class Task extends Model
{
    use IsRelayConnection;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
