<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\DBTestCase;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Foreign keys
 * @property int|null $user_id
 *
 * Relations
 * @property-read \Tests\Utils\Models\User|null $user
 */
final class AlternateConnection extends Model
{
    public $timestamps = false;

    protected $connection = DBTestCase::ALTERNATE_CONNECTION;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
