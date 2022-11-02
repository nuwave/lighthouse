<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Tests\Utils\LaravelEnums\AOrB;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property string|null $name
 * @property AOrB|null $type
 *
 * Scopes
 *
 * @method static \Illuminate\Database\Eloquent\Builder&static byType(AOrB $aOrB)
 * @method static \Illuminate\Database\Eloquent\Builder&static byTypeInternal(string $aOrB) TODO remove in v6
 */
final class WithEnum extends Model
{
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
