<?php declare(strict_types=1);

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Primary key.
 *
 * @property int $id
 *
 * Attributes
 * @property bool $create_post
 * @property bool $read_post
 * @property bool $update_post
 * @property bool $delete_post
 */
final class ACL extends Model
{
    protected $table = 'acls';

    /** @var bool */
    public $timestamps = false;
}
