<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property bool $create_post
 * @property bool $read_post
 * @property bool $update_post
 * @property bool $delete_post
 */
class ACL extends Model
{
    public $timestamps = false;
    protected $table = 'acls';
    protected $guarded = [];
}
