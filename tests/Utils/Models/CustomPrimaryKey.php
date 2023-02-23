<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Primary key.
 *
 * @property int $custom_primary_key_id
 *
 * Foreign keys
 * @property int|null $user_id
 *
 * Relations
 * @property-read \Tests\Utils\Models\User|null $user
 */
final class CustomPrimaryKey extends Model
{
    protected $primaryKey = 'custom_primary_key_id';

    public $timestamps = false;

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\Tests\Utils\Models\User, self>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
