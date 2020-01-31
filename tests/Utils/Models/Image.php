<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Image extends Model
{
    protected $guarded = [];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
