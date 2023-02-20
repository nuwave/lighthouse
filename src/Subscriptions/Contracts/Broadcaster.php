<?php

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;

interface Broadcaster
{
    public const EVENT_NAME = 'lighthouse-subscription';

    /**
     * Handle authorized subscription request.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function authorized(Request $request);

    /**
     * Handle unauthorized subscription request.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function unauthorized(Request $request);

    /**
     * Handle subscription web hook.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function hook(Request $request);

    /**
     * Send data to subscriber.
     *
     * @param  mixed  $data  The data to broadcast
     *
     * @return void
     */
    public function broadcast(Subscriber $subscriber, $data);
}
