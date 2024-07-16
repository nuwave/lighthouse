<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Tests\Utils\Models\Car;
use Tests\Utils\Models\Owner;



/**
 * Primary key.
 * @property int $id
 * @property string $name
 *
 *   Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Car|null $car
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Owner|null $owner
 */
final class Mechanic extends Model
{

    public function car(): HasOne
    {
        return $this->hasOne(Car::class);
    }
    public function owner(): HasOneThrough
    {
        return $this->hasOneThrough(Owner::class, Car::class);
    }

}
