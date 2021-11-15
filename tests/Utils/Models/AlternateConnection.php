<?php

namespace Tests\Utils\Models;

use BenSampo\Enum\Traits\CastsEnums;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\DBTestCase;
use Tests\Utils\LaravelEnums\AOrB;

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
class AlternateConnection extends Model
{
    public $timestamps = false;

    protected $connection = DBTestCase::ALTERNATE_CONNECTION;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
