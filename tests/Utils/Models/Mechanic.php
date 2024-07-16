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
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Car> $car
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Owner> $owner
 */
final class Mechanic extends Model
{

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<\Tests\Utils\Models\Car> */
    public function car(): HasOne
    {
        return $this->hasOne(Car::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasOneThrough<\Tests\Utils\Models\Owner> */
    public function owner(): HasOneThrough
    {
        return $this->hasOneThrough(Owner::class, Car::class);
    }

}
