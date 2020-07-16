<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Lead extends Model
{
    protected $primaryKey = 'user_id';
    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Get the 'user' that owns the 'lead' record.
     */
    public function user() : BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the 'tasks' that belongs to 'lead' record.
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'agent_task', 'lead_id');
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::saving(function ($Lead) {

            if(empty($Lead->uid))
            {
                $Lead->uid = Uid::create()->uid;
            }

            $Role = Role::where('key', 'lead')->first();
            $User = User::where('id', $Lead->user_id)->first();
            $User->roles()->attach($Role->id);
        });
    }
}
