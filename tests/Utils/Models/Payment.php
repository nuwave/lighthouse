<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    public function fulfilledBy(): MorphTo
    {
        return $this->morphTo();
    }
}
