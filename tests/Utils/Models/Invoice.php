<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    public function lineItems(): MorphMany
    {
        return $this->morphMany(PayableLineItem::class, "object");
    }

}
