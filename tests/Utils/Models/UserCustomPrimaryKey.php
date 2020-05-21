<?php

namespace Tests\Utils\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string|null $name
 */
class UserCustomPrimaryKey extends Authenticatable
{
    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $table = 'users_custom_primary_key';

    protected static function boot()
    {
        parent::boot();

        static::creating(function($model) {
            $model->attributes['uuid'] = Str::uuid();
        });
    }
}
