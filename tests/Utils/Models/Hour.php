<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Hour extends Model
{
    protected $guarded = [];

    public function hourable(): MorphTo
    {
        return $this->morphTo();
    }
}
