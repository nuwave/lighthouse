<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function users(): BelongsToMany
    {
        return $this
            ->belongsToMany(User::class)
            ->withPivot(['meta']);
    }

    public function acl(): BelongsTo
    {
        return $this->belongsTo(ACL::class);
    }
}
