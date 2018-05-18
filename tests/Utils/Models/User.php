<?php

namespace Tests\Utils\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Nuwave\Lighthouse\Support\Traits\IsRelayConnection;

class User extends Authenticatable
{
    use IsRelayConnection;

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * @param $query User
     * @param $args
     * @return mixed
     */
    public function scopeCompanyName($query, $args)
    {
        return $query->whereHas("company", function($q) use ($args){
            $q->where("name", $args['company']);
        });

    }
}
