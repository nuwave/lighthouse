<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PayableLineItem extends Model
{
    public function object(): MorphTo
    {
        return $this->morphTo();
    }

    public function fulfills(): MorphOne
    {
        return $this->morphOne(Payment::class, "fulfilled_by");
    }

}
