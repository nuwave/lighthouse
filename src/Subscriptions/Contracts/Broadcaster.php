<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

interface Broadcaster
{
    /**
     * Handle authorized subscription request.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    public function authorized(Request $request);

    /**
     * Handle unauthorized subscription request.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    public function unauthorized(Request $request);

    /**
     * Handle subscription web hook.
     *
     * @param  Request  $request
     *
     * @return Response
     */
    public function hook(Request $request);

    /**
     * Send data to subscriber.
     *
     * @param  Subscriber  $subscriber
     * @param  mixed[]    $data
     */
    public function broadcast(Subscriber $subscriber, array $data);
}
