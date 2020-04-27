<?php

namespace Tests\Utils\Models;

use BenSampo\Enum\Traits\CastsEnums;
use Illuminate\Database\Eloquent\Model;
use Tests\Utils\LaravelEnums\AOrB;

/**
 * @property int $id
 * @property string|null $name
 * @property AOrB|null $type
 */
class WithEnum extends Model
{
    use CastsEnums;

    protected $guarded = [];
    public $timestamps = false;

    protected $enumCasts = [
        'type' => AOrB::class,
    ];
}
