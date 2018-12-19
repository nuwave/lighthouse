<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

interface Broadcaster
{
    /**
     * Handle authorized subscription request.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function authorized(Request $request);

    /**
     * Handle unauthorized subscription request.
     *
     * @param Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function unauthorized(Request $request);

    /**
     * Handle subscription web hook.
     *
     * @param Request $request
     *
     * @return \Illuminate\Support\Response
     */
    public function hook(Request $request);

    /**
     * Send data to subscriber.
     *
     * @param Subscriber $subscriber
     * @param array      $data
     */
    public function broadcast(Subscriber $subscriber, array $data);
}
