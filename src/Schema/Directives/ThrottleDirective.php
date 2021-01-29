<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\RateLimitException;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Symfony\Component\HttpFoundation\Response;

class ThrottleDirective extends BaseDirective implements FieldMiddleware
{
    /**
     * The rate limiter instance.
     *
     * @var \Illuminate\Cache\RateLimiter
     */
    protected $limiter;

    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Create a new request throttler.
     */
    public function __construct(RateLimiter $limiter, Request $request)
    {
        $this->limiter = $limiter;
        $this->request = $request;
    }

    public static function definition(): string
    {
        return <<<'GRAPHQL'
"""
Sets rate limit to access the field. Does the same as ThrottleRequests Laravel Middleware.
"""
directive @throttle (
    """
    Named preconfigured rate limiter.
    """
    name: String

    """
    Maximum number of attempts in a specified time interval.
    """
    maxAttempts: Int

    """
    Time in minutes to reset attempts.
    """
    decayMinutes: Float

    """
    Prefix to distinguish several field groups.
    """
    prefix: String

) on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        $originalResolver = $fieldValue->getResolver();

        $limits = [];
        $name = $this->directiveArgValue('name', null);
        if ($name !== null) {
            $limiter = $this->limiter->limiter($name);
            /** @phpstan-ignore-next-line $limiter may be null although it's not specified in limiter() PHPDoc */
            if (is_null($limiter)) {
                throw new DirectiveException("Named limiter $name is not found.");
            }

            $limiterResponse = call_user_func($limiter, $this->request);
            if ($limiterResponse instanceof Unlimited) {
                return $next($fieldValue);
            }

            if ($limiterResponse instanceof Response) {
                throw new DirectiveException(
                    "Named limiter $name returns instance of Responst which is not supported."
                );
            }

            foreach (Arr::wrap($limiterResponse) as $limit) {
                $limits[] = [
                    'key' => sha1($name.$limit->key),
                    'maxAttempts' => $limit->maxAttempts,
                    'decayMinutes' => $limit->decayMinutes,
                ];
            }
        } else {
            $limits[] = [
                'key' => sha1($this->directiveArgValue('prefix', '').$this->request->ip()),
                'maxAttempts' => $this->directiveArgValue('maxAttempts', 60),
                'decayMinutes' => $this->directiveArgValue('decayMinutes', 1),
            ];
        }

        return $next(
            $fieldValue->setResolver(
                function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use (
                    $originalResolver,
                    $limits
                ) {
                    foreach ($limits as $limit) {
                        $this->handleLimit(
                            $limit['key'],
                            $limit['maxAttempts'],
                            $limit['decayMinutes']
                        );
                    }

                    return $originalResolver($root, $args, $context, $resolveInfo);
                }
            )
        );
    }

    /**
     * Checks throttling limit.
     */
    protected function handleLimit(string $key, int $maxAttempts, float $decayMinutes): void
    {
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            throw new RateLimitException();
        }

        $this->limiter->hit($key, (int) ($decayMinutes * 60));
    }
}
