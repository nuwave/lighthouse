<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Support\Traits\IsRelayConnection;

class Company extends Model
{
    use IsRelayConnection;

    protected $visible = ['id'];
}
