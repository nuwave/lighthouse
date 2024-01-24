<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\Type;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
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
Allow clients to specify the maximum number of results to return when used on an argument,
or statically limits them when used on a field.

This directive does not influence the number of results the resolver queries internally,
but limits how much of it is returned to clients.
"""
directive @limit on ARGUMENT_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        $argType = ASTHelper::getUnderlyingTypeName($argDefinition->type);
        $expectedArgType = Type::INT;
        if ($expectedArgType !== $argType) {
            throw new DefinitionException("The {$this->name()} directive must only be used on arguments of type {$expectedArgType}, got {$parentField->name->value}.{$this->nodeName()} of type {$argType}.");
        }

        $parentField->directives[] = $this->directiveNode;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        $fieldValue->resultHandler(static function (?iterable $result, array $args, GraphQLContext $context, ResolveInfo $resolveInfo): ?iterable {
            if ($result === null) {
                return null;
            }

            $limit = null;
            foreach ($resolveInfo->argumentSet->arguments as $argument) {
                $argumentIsUsedToLimit = $argument->directives->contains(
                    Utils::instanceofMatcher(self::class),
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
                if ($limit === 0) {
                    break;
                }

                --$limit;

                $limited[] = $value;
            }

            return $limited;
        });
    }
}
