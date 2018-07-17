<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Nuwave\Lighthouse\Support\Traits\IsRelayConnection;

class Task extends Model
{
    use IsRelayConnection, SoftDeletes;

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
