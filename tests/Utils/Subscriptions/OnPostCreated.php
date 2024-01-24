<?php declare(strict_types=1);

namespace Tests\Utils\Subscriptions;

use Illuminate\Http\Request;
use Nuwave\Lighthouse\Schema\Types\GraphQLSubscription;
use Nuwave\Lighthouse\Subscriptions\Subscriber;
use Tests\Utils\Models\User;

final class OnPostCreated extends GraphQLSubscription
{
    public function authorize(Subscriber $subscriber, Request $request): bool
    {
        $user = $request->user();

        return ! $user
            || ! $user instanceof User
            || $user->name !== 'fail_the_authorize_of_subscription';
    }

    public function filter(Subscriber $subscriber, mixed $root): bool
    {
        return true;
    }
}
