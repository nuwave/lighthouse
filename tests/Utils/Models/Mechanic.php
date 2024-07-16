<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;


/**
 * Primary key.
 * @property int $id
 * @property string $title
 * @property-read \Tests\Utils\Models\Owner $owner
 *
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
