<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Subscriptions\Contracts;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Symfony\Component\HttpFoundation\Response;

interface Broadcaster
{
    public const EVENT_NAME = 'lighthouse-subscription';

    /** Handle authorized subscription request. */
    public function authorized(Request $request): Response;

    /** Handle unauthorized subscription request. */
    public function unauthorized(Request $request): Response;

    /** Handle subscription web hook. */
    public function hook(Request $request): Response;

    /** Send data to subscriber. */
    public function broadcast(Subscriber $subscriber, mixed $data): void;
}
