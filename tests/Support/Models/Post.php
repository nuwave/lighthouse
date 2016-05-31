<?php

namespace Nuwave\Lighthouse\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Traits\RelayModel;

class Post extends Model
{
    use RelayModel;

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'post_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title', 'content', 'user_id',
    ];
}
