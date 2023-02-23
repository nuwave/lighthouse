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
 * @method static \Illuminate\Database\Eloquent\Builder&static byType(AOrB $aOrB)
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
}
