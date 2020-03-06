<?php

namespace Tests\Utils\Models;

use BenSampo\Enum\Traits\CastsEnums;
use Illuminate\Database\Eloquent\Model;
use Tests\Utils\LaravelEnums\AOrB;

class WithEnum extends Model
{
    use CastsEnums;

    protected $guarded = [];
    public $timestamps = false;

    protected $enumCasts = [
        'type' => AOrB::class,
    ];
}
