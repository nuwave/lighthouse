<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;


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
    public function owner(): HasOne
    {
        return $this->hasOne(Owner::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(Mechanic::class);
    }
}
