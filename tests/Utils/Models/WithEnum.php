<?php

namespace Tests\Utils\Models;

use BenSampo\Enum\Traits\CastsEnums;
use Illuminate\Database\Eloquent\Model;
use Tests\Utils\LaravelEnums\AOrB;

/**
 * Primary key.
 * @property int $id
 *
 * Attributes
 * @property string|null $name
 * @property AOrB|null $type
 */
class WithEnum extends Model
{
    use CastsEnums;

    public $timestamps = false;

    /**
     * @var array<string, class-string<\BenSampo\Enum\Enum>>
     */
    protected $enumCasts = [
        'type' => AOrB::class,
    ];
}
