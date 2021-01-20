<?php

namespace Nuwave\Lighthouse\Schema\Directives;

use Closure;
use GraphQL\Deferred;
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
directive @limit on ARGUMENT_DEFINITION
GRAPHQL;
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode &$parentType
    ): void {
        $argType = ASTHelper::getUnderlyingTypeName($argDefinition->type);
        if ($argType !== Type::INT) {
            throw new DefinitionException(
                "The {$this->name()} directive must only be used on arguments of type ".Type::INT
                .", got {$argType} on {$parentField->name->value}.{$this->nodeName()}."
            );
        }

        // TODO change once we can depend on https://github.com/webonyx/graphql-php/pull/767
        // $parentField->directives [] = $this->directiveNode;
        $parentField->directives = ASTHelper::mergeNodeList($parentField->directives, [$this->directiveNode]);
    }

    public function handleField(FieldValue $fieldValue, Closure $next)
    {
        $previousResolver = $fieldValue->getResolver();

        $fieldValue->setResolver(function ($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo) use ($previousResolver) {
            $result = $previousResolver($root, $args, $context, $resolveInfo);

            if ($result instanceof Deferred) {
                return $result->then(function ($result) use ($resolveInfo) {
                    return $this->limitResult($result, $resolveInfo);
                });
            }

            return $this->limitResult($result, $resolveInfo);
        });

        return $fieldValue;
    }

    /**
     * @param  iterable<mixed>|null  $result
     * @return iterable<mixed>
     */
    protected function limitResult(?iterable $result, ResolveInfo $resolveInfo): ?iterable
    {
        if ($result === null) {
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
        if (! is_integer($limit)) {
            return $result;
        }

        $limited = [];

        foreach ($result as $value) {
            if ($limit === 0) {
                break;
            }
            $limit--;

            $limited [] = $value;
        }

        return $limited;
    }
}
