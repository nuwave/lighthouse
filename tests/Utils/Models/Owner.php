<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\Utils\Models\Car;


/**
 * Primary key.
 * @property int $id
 * @property string $name
 *
 *   Foreign keys
 * @property int|null $car_id
 *
 *   Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Car> $car
 */

final class Owner extends Model
{
    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\Car> */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
