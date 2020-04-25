<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property boolean $create_post
 * @property boolean $read_post
 * @property boolean $update_post
 * @property boolean $delete_post
 */
class ACL extends Model
{
    public $timestamps = false;
    protected $table = 'acls';
    protected $guarded = [];
}
