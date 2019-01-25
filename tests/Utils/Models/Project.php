<?php

namespace Tests\Utils\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $guarded = [];

    public function getLighthouseKeyName(): string
    {
        return 'uuid';
    }
}
