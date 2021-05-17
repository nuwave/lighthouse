<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Primary key.
 * @property int $id
 *
 * Attributes
 * @property string $position
 *
 * Timestamps
 * @property \lluminate\Support\Carbon $created_at
 * @property \lluminate\Support\Carbon $updated_at
 *
 * Relations
 * @property-read \Tests\Utils\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Color> $colors
 */
class Contractor extends Model
{
    public function user(): MorphOne
    {
        return $this->morphOne(User::class, 'person');
    }

    public function colors(): MorphMany
    {
        return $this->morphMany(Color::class, 'creator');
    }
}
