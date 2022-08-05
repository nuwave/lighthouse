<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Exceptions\DirectiveException;
use Nuwave\Lighthouse\Exceptions\RateLimitException;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\AppVersion;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Symfony\Component\HttpFoundation\Response;

class ThrottleDirective extends BaseDirective implements FieldMiddleware, FieldManipulator
{
    /**
     * @var \Illuminate\Cache\RateLimiter
     */
    protected $limiter;

    /**
     * @var \Illuminate\Http\Request
     */
    protected $request;

    public function __construct(RateLimiter $limiter, Request $request)
    {
        $this->limiter = $limiter;
        $this->request = $request;
    }

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Sets rate limit to access the field. Does the same as ThrottleRequests Laravel Middleware.
"""
directive @throttle(
    """
    Named preconfigured rate limiter. Requires Laravel 8.x or later.
    """
    name: String

    """
    Maximum number of attempts in a specified time interval.
    """
    maxAttempts: Int = 60

    """
    Time in minutes to reset attempts.
    """
    decayMinutes: Float = 1.0

    """
    Prefix to distinguish several field groups.
    """
    prefix: String
) on FIELD_DEFINITION
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue, Closure $next): FieldValue
    {
        /** @var array<int, array{key: string, maxAttempts: int, decayMinutes: float}> $limits */
        $limits = [];

        $name = $this->directiveArgValue('name');
        if (null !== $name) {
            // @phpstan-ignore-next-line won't be executed on Laravel < 8
            $limiter = $this->limiter->limiter($name);

            $limiterResponse = $limiter($this->request);
            // @phpstan-ignore-next-line won't be executed on Laravel < 8
            if ($limiterResponse instanceof Unlimited) {
                return $next($fieldValue);
            }

            if ($limiterResponse instanceof Response) {
                throw new DirectiveException(
                    "Expected named limiter {$name} to return an array, got instance of " . get_class($limiterResponse)
                );
            }

            foreach (Arr::wrap($limiterResponse) as $limit) {
                $limits[] = [
                    'key' => sha1($name . $limit->key),
                    'maxAttempts' => $limit->maxAttempts,
                    'decayMinutes' => $limit->decayMinutes,
                ];
            }
        } else {
            $limits[] = [
                'key' => sha1($this->directiveArgValue('prefix') . $this->request->ip()),
                'maxAttempts' => $this->directiveArgValue('maxAttempts', 60),
                'decayMinutes' => $this->directiveArgValue('decayMinutes', 1.0),
            ];
        }

        $resolver = $fieldValue->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $limits) {
            foreach ($limits as $limit) {
                $this->handleLimit(
                    $limit['key'],
                    $limit['maxAttempts'],
                    $limit['decayMinutes'],
                    "{$resolveInfo->parentType}.{$resolveInfo->fieldName}"
                );
            }

            return $resolver($root, $args, $context, $resolveInfo);
        });

        return $next($fieldValue);
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode &$parentType): void
    {
        $name = $this->directiveArgValue('name');
        if (null !== $name) {
            if (AppVersion::below(8.0)) {
                throw new DefinitionException('Named limiter requires Laravel 8.x or later');
            }

            // @phpstan-ignore-next-line won't be executed on Laravel < 8
            $limiter = $this->limiter->limiter($name);
            // @phpstan-ignore-next-line $limiter may be null although it's not specified in limiter() PHPDoc
            if (null === $limiter) {
                throw new DefinitionException("Named limiter {$name} is not found.");
            }
        }
    }

    /**
     * Checks throttling limit and records this attempt.
     */
    protected function handleLimit(string $key, int $maxAttempts, float $decayMinutes, string $fieldReference): void
    {
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            throw new RateLimitException($fieldReference);
        }

        $this->limiter->hit($key, (int) ($decayMinutes * 60));
    }
}
