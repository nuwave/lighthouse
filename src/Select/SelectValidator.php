<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Select;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use Nuwave\Lighthouse\Auth\CanDirective;
use Nuwave\Lighthouse\Auth\CanResolvedDirective;
use Nuwave\Lighthouse\Auth\CanRootDirective;
use Nuwave\Lighthouse\Events\ManipulateAST;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Pagination\PaginateDirective;
use Nuwave\Lighthouse\Pagination\PaginationType;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;
use Nuwave\Lighthouse\Schema\AST\DocumentAST;
use Nuwave\Lighthouse\Schema\DirectiveLocator;
use Nuwave\Lighthouse\Schema\Directives\RelationDirective;

class SelectValidator
{
    public function __construct(
        protected SelectColumns $selectColumns,
        protected DirectiveLocator $directiveLocator,
    ) {}

    public function handle(ManipulateAST $manipulateAST): void
    {
        $documentAST = $manipulateAST->documentAST;

        foreach ($documentAST->types as $type) {
            if (! $type instanceof ObjectTypeDefinitionNode) {
                continue;
            }

            $optsIn = $this->selectColumns->optsIn($type);

            foreach ($type->fields as $field) {
                if ($optsIn) {
                    $this->validateField($type, $field);
                }

                if ($this->checksPolicyOn($field, CanResolvedDirective::class, 'resolved')) {
                    $resolvedType = $this->resolvedType($field, $documentAST);
                    if ($resolvedType !== null && $this->selectColumns->optsIn($resolvedType)) {
                        $this->requireTypeColumns($resolvedType, "{$type->name->value}.{$field->name->value}");
                    }
                }
            }
        }
    }

    protected function validateField(ObjectTypeDefinitionNode $type, FieldDefinitionNode $field): void
    {
        $fieldPath = "{$type->name->value}.{$field->name->value}";

        if (! $this->selectColumns->declaresColumns($field)) {
            throw new DefinitionException("Add @select or a relation directive to {$fieldPath}, because type {$type->name->value} uses @select.");
        }

        if ($this->checksPolicyOn($field, CanRootDirective::class, 'root')) {
            $this->requireTypeColumns($type, $fieldPath);
        }
    }

    protected function requireTypeColumns(ObjectTypeDefinitionNode $type, string $fieldPath): void
    {
        if ($this->selectColumns->selectDirective($type) === null) {
            throw new DefinitionException("Add @select to type {$type->name->value} with the columns its policies need, because {$fieldPath} checks a policy against it.");
        }
    }

    /**
     * Also matches the equivalent argument of @can.
     *
     * @param  class-string<\Nuwave\Lighthouse\Support\Contracts\Directive>  $directiveClass
     */
    protected function checksPolicyOn(FieldDefinitionNode $field, string $directiveClass, string $canArgument): bool
    {
        foreach ($field->directives as $directive) {
            $resolvedClass = $this->directiveLocator->resolve($directive->name->value);

            if (is_a($resolvedClass, $directiveClass, true)) {
                return true;
            }

            // TODO remove with v7, together with @can
            if (is_a($resolvedClass, CanDirective::class, true) && ASTHelper::directiveArgValue($directive, $canArgument) === true) {
                return true;
            }
        }

        return false;
    }

    /** Unwraps pagination to find the items type. */
    protected function resolvedType(FieldDefinitionNode $field, DocumentAST $documentAST): ?ObjectTypeDefinitionNode
    {
        $type = $documentAST->types[ASTHelper::getUnderlyingTypeName($field)] ?? null;
        if (! $type instanceof ObjectTypeDefinitionNode) {
            return null;
        }

        foreach ($this->paginationType($field)?->itemsPath() ?? [] as $fieldName) {
            $itemsField = ASTHelper::firstByName($type->fields, $fieldName);
            if (! $itemsField instanceof FieldDefinitionNode) {
                return null;
            }

            $type = $documentAST->types[ASTHelper::getUnderlyingTypeName($itemsField)] ?? null;
            if (! $type instanceof ObjectTypeDefinitionNode) {
                return null;
            }
        }

        return $type;
    }

    protected function paginationType(FieldDefinitionNode $field): ?PaginationType
    {
        foreach ($field->directives as $directive) {
            $directiveClass = $this->directiveLocator->resolve($directive->name->value);
            $paginationTypeName = ASTHelper::directiveArgValue($directive, 'type');

            if (is_a($directiveClass, PaginateDirective::class, true)) {
                return new PaginationType($paginationTypeName ?? PaginationType::PAGINATOR);
            }

            // Enforced before relations are optimized, so that optimizing them is not breaking
            if (is_a($directiveClass, RelationDirective::class, true) && $paginationTypeName !== null) {
                return new PaginationType($paginationTypeName);
            }
        }

        return null;
    }
}
