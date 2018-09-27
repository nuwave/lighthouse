<?php

namespace Nuwave\Lighthouse\Subscriptions\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int                                      $id
 * @property string                                   $key
 * @property \Illuminate\Database\Eloquent\Collection $subscriptions
 */
class Topic extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lighthouse_topics';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['key'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }
}
