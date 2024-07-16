<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\Utils\Models\Mechanic;
use Tests\Utils\Models\Owner;


/**
 * Primary key.
 * @property int $id
 * @property string $name
 *
 *  Foreign keys
 * @property int|null $mechanic_id
 *
 *  Relations
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Owner> $owner
*/

final class Car extends Model
{
    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<Owner> */
    public function owner(): HasOne
    {
        return $this->hasOne(Owner::class);
    }

}
