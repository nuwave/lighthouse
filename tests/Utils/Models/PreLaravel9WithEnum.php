<?php

namespace Tests\Utils\Models;

use BenSampo\Enum\Traits\CastsEnums;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Tests\Utils\LaravelEnums\AOrB;

/**
 * TODO remove when requiring Laravel 9+.
 */
final class PreLaravel9WithEnum extends Model
{
    use CastsEnums;

    protected $table = 'with_enums';

    public $timestamps = false;

    /**
     * @var array<string, class-string<\BenSampo\Enum\Enum>>
     */
    protected $enumCasts = [
        'type' => AOrB::class,
    ];

    public function scopeByType(EloquentBuilder $builder, AOrB $aOrB): EloquentBuilder
    {
        return $builder->where('type', $aOrB);
    }

    /**
     * TODO remove in v6.
     */
    public function scopeByTypeInternal(EloquentBuilder $builder, string $aOrB): EloquentBuilder
    {
        return $builder->where('type', $aOrB);
    }
}
