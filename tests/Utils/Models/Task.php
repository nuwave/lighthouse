<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Traits\IsRelayConnection;

class Task extends Model
{
    use IsRelayConnection;

    protected $visible = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
