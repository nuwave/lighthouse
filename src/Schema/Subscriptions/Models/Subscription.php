<?php

namespace Nuwave\Lighthouse\Schema\Subscriptions\Models;

use Nuwave\Lighthouse\Schema\Subscriptions\Subscriber;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int     $id
 * @property int     $channel_id
 * @property array   $args
 * @property string  $context
 * @property string  $operation_name
 * @property string  $query_string
 * @property Channel $channel
 */
class Subscription extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'lighthouse_subscriptions';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = ['args' => 'array'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'channel',
        'args',
        'context',
        'operation_name',
        'query_string',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * @return Subscriber
     */
    public function toSubscriber()
    {
        return Subscriber::unserialize(json_encode([
            'channel' => $this->channel,
            'context' => $this->context,
            'args' => $this->args,
            'operation_name' => $this->operation_name,
            'query_string' => $this->query_string,
        ]));
    }
}
