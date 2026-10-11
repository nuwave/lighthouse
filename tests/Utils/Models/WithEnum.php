<?php declare(strict_types=1);

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
 * @method static \Illuminate\Database\Eloquent\Builder<self> byType(AOrB $aOrB)
 */
final class WithEnum extends Model
{
    public $timestamps = false;

    // @phpstan-ignore-next-line iterable type missing in Laravel 9.0.0
    protected $casts = [
        'type' => AOrB::class,
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $builder
     *
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeByType(EloquentBuilder $builder, AOrB $aOrB): EloquentBuilder
    {
        return $builder->where('type', $aOrB);
    }
}
