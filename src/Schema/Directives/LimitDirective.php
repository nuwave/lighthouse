<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class LimitDirective extends BaseDirective implements ArgDirective, ArgManipulator, FieldMiddleware
{
    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Allow clients to specify the maximum number of results to return.
"""
directive @limit on ARGUMENT_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        $argType = ASTHelper::getUnderlyingTypeName($argDefinition->type);
        if (Type::INT !== $argType) {
            throw new DefinitionException(
                "The {$this->name()} directive must only be used on arguments of type " . Type::INT
                . ", got {$argType} on {$parentField->name->value}.{$this->nodeName()}."
            );
        }

        $parentField->directives[] = $this->directiveNode;
    }

    public function handleField(FieldValue $fieldValue, Closure $next)
    {
        $fieldValue->resultHandler(static function (?iterable $result, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ?iterable {
            if (null === $result) {
                return null;
            }

            $limit = null;
            foreach ($resolveInfo->argumentSet->arguments as $argument) {
                $argumentIsUsedToLimit = $argument->directives->contains(
                    Utils::instanceofMatcher(self::class)
                );

                if ($argumentIsUsedToLimit) {
                    $limit = $argument->value;
                    break;
                }
            }

            // Do not apply a limit if the client passes null explicitly
            if (! is_int($limit)) {
                return $result;
            }

            $limited = [];

            foreach ($result as $value) {
                if (0 === $limit) {
                    break;
                }
                --$limit;

                $limited[] = $value;
            }

            return $limited;
        });

        return $next($fieldValue);
    }
}
