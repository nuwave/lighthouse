<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Schema\Directives;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Execution\ResolveInfo;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\Values\FieldValue;
use Nuwave\Lighthouse\Support\Contracts\ArgBuilderDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgDirective;
use Nuwave\Lighthouse\Support\Contracts\ArgManipulator;
use Nuwave\Lighthouse\Support\Contracts\FieldMiddleware;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Nuwave\Lighthouse\Support\Utils;

class LimitDirective extends BaseDirective implements ArgDirective, ArgBuilderDirective, ArgManipulator, FieldMiddleware
{
    public static function definition(): string
    {
        return /* @lang GraphQL */ <<<'GRAPHQL'
"""
Allow clients to specify the maximum number of results to return when used on an argument,
or statically limit them when used on a field.

By default, this directive does not influence the number of results the resolver queries internally,
but limits how much of it is returned to clients. Use the `builder` argument to change this.
"""
directive @limit(
    """
    You may set this to `true` if the field uses a query builder,
    then this directive will apply a LIMIT clause to it.
    Typically, this option should only be used for root fields,
    as it may cause wrong results with batched relation queries.
    """
    builder: Boolean! = false
) on ARGUMENT_DEFINITION | FIELD_DEFINITION
GRAPHQL;
    }

    public function manipulateArgDefinition(
        DocumentAST &$documentAST,
        InputValueDefinitionNode &$argDefinition,
        FieldDefinitionNode &$parentField,
        ObjectTypeDefinitionNode|InterfaceTypeDefinitionNode &$parentType,
    ): void {
        if ($this->shouldApplyToQueryBuilders()) {
            return;
        }

        $argType = ASTHelper::getUnderlyingTypeName($argDefinition->type);
        $expectedArgType = Type::INT;
        if ($expectedArgType !== $argType) {
            throw new DefinitionException("The {$this->name()} directive must only be used on arguments of type {$expectedArgType}, got {$parentField->name->value}.{$this->nodeName()} of type {$argType}.");
        }

        $parentField->directives[] = $this->directiveNode;
    }

    public function handleField(FieldValue $fieldValue): void
    {
        if ($this->shouldApplyToQueryBuilders()) {
            return;
        }

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

    public function handleBuilder(Relation|EloquentBuilder|QueryBuilder $builder, mixed $value): QueryBuilder|EloquentBuilder|Relation
    {
        if (! $this->shouldApplyToQueryBuilders()) {
            return $builder;
        }

        assert(is_int($value));

        return $builder->limit($value);
    }

    protected function shouldApplyToQueryBuilders(): bool
    {
        return $this->directiveArgValue('builder') ?? false;
    }
}
