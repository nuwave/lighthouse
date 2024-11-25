<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property string $position
 *
 * Timestamps
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Relations
 * @property-read \Tests\Utils\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Color> $colors
 */
final class Employee extends Model
{
    /** @return \Illuminate\Database\Eloquent\Relations\MorphOne<\Tests\Utils\Models\User, $this> */
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'person');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\MorphMany<\Tests\Utils\Models\Color, $this> */
    public function colors(): MorphMany
    {
        return $this->morphMany(Color::class, 'creator');
    }
}
