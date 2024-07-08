<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;


/**
 * Primary key.
 * @property int $id
 * @property string $title
 *  Timestamps
 * @property \Illuminate\Support\Carbon $schedule_at
*/

final class Podcast extends Model
{
    protected static function booted(): void
    {
        self::addGlobalScope("published", function (Builder $q) {
            $q->whereNotNull("schedule_at");
        });
    }
}
