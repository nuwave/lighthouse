<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Auth;

use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Nuwave\Lighthouse\Exceptions\AuthenticationException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Contracts\TypeExtensionManipulator;
use Nuwave\Lighthouse\Support\Contracts\TypeManipulator;

/**
 * @see \Illuminate\Auth\Middleware\Authenticate
 */
class GuardDirective extends BaseDirective implements FieldMiddleware, TypeManipulator, TypeExtensionManipulator
{
    public function __construct(
        protected AuthFactory $authFactory,
    ) {}

    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Run authentication through one or more guards from `config/auth.php`.

This is run per field and may allow unauthenticated
users to still receive partial results.

Used upon an object, it applies to all fields within.
"""
directive @guard(
  """
  Specify which guards to use, e.g. ["web"].
  When not defined, the default from `lighthouse.php` is used.
  """
  with: [String!]
) repeatable on FIELD_DEFINITION | OBJECT
GRAPHQL;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->wrapResolver(fn (callable $resolver): \Closure => function (mixed $root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($resolver) {
            $guards = $this->directiveArgValue('with', AuthServiceProvider::guards());
            $context->setUser($this->authenticate($guards));

            return $resolver($root, $args, $context, $resolveInfo);
        });
    }

    /**
     * Determine if the user is logged in to any of the given guards.
     *
     * @param  array<string|null>  $guards
     */
    protected function authenticate(array $guards): Authenticatable
    {
        foreach ($guards as $guard) {
            $user = $this->authFactory->guard($guard)->user();

            if ($user !== null) {
                // @phpstan-ignore-next-line passing null works fine here
                $this->authFactory->shouldUse($guard);

                return $user;
            }
        }

        $this->unauthenticated($guards);
    }

    /**
     * Handle an unauthenticated user.
     *
     * @param  array<string|null>  $guards
     *
     * @return never
     */
    protected function unauthenticated(array $guards): void
    {
        throw new AuthenticationException(AuthenticationException::MESSAGE, $guards);
    }

    public function manipulateTypeDefinition(DocumentAST &$documentAST, TypeDefinitionNode &$typeDefinition): void
    {
        ASTHelper::addDirectiveToFields($this->directiveNode, $typeDefinition);
    }

    public function manipulateTypeExtension(DocumentAST &$documentAST, TypeExtensionNode &$typeExtension): void
    {
        ASTHelper::addDirectiveToFields($this->directiveNode, $typeExtension);
    }
}
