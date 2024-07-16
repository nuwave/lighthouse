<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\Utils\Models\Owner;
use Tests\Utils\Models\Mechanic;


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
 * @property-read \Illuminate\Database\Eloquent\Collection<\Tests\Utils\Models\Mechanic> $mechanic
*/

final class Car extends Model
{

    /** @return \Illuminate\Database\Eloquent\Relations\HasOne<\Tests\Utils\Models\Owner> */
    public function owner(): HasOne
    {
        return $this->hasOne(Owner::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\Mechanic, self> */

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }
}
