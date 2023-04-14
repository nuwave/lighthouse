<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\RateLimiting\Unlimited;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Exceptions\RateLimitException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Symfony\Component\HttpFoundation\Response;

class ThrottleDirective extends BaseDirective implements FieldMiddleware, FieldManipulator
{
    public function __construct(
        protected RateLimiter $limiter,
        protected Request $request,
    ) {}

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Sets rate limit to access the field. Does the same as ThrottleRequests Laravel Middleware.
"""
directive @throttle(
    """
    Named preconfigured rate limiter.
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

    public function handleField(FieldValue $fieldValue): void
    {
        /** @var array<int, array{key: string, maxAttempts: int, decayMinutes: float}> $limits */
        $limits = [];

        $name = $this->directiveArgValue('name');
        if ($name !== null) {
            // @phpstan-ignore-next-line limiter() can actually return null, some Laravel versions lie
            $limiter = $this->limiter->limiter($name)
                ?? throw new DefinitionException("Named limiter {$name} not found.");

            $limiterResponse = $limiter($this->request);
            if ($limiterResponse instanceof Unlimited) {
                return;
            }

            if ($limiterResponse instanceof Response) {
                throw new DefinitionException("Expected named limiter {$name} to return an array, got instance of " . $limiterResponse::class);
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

        $fieldValue->wrapResolver(fn (callable $resolver): \Closure => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver, $limits) {
            foreach ($limits as $limit) {
                $this->handleLimit(
                    $limit['key'],
                    $limit['maxAttempts'],
                    $limit['decayMinutes'],
                    "{$resolveInfo->parentType}.{$resolveInfo->fieldName}",
                );
            }

            return $resolver($root, $args, $context, $resolveInfo);
        });
    }

    public function manipulateFieldDefinition(DocumentAST &$documentAST, FieldDefinitionNode &$fieldDefinition, ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType): void
    {
        $name = $this->directiveArgValue('name');
        if ($name !== null) {
            // @phpstan-ignore-next-line $limiter may be null although it's not specified in limiter() PHPDoc
            $this->limiter->limiter($name)
                ?? throw new DefinitionException("Named limiter {$name} is not found.");
        }
    }

    /** Checks throttling limit and records this attempt. */
    protected function handleLimit(string $key, int $maxAttempts, float $decayMinutes, string $fieldReference): void
    {
        if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
            throw new RateLimitException($fieldReference);
        }

        $this->limiter->hit($key, (int) ($decayMinutes * 60));
    }
}
