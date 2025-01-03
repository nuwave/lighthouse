<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\TypeNode;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

/**
 * @template-covariant TClass of object
 * @property-read class-string<TClass> $class
 * @property-read string $column
 * @property-read array<string> $with
 * @property-read bool $optional
 */
class BindDefinition
{
    private const SUPPORTED_VALUE_TYPES = [
        Type::ID,
        Type::STRING,
        Type::INT,
    ];

    public function __construct(
        /** @param class-string<TClass> $class */
        public string $class,
        public string $column,
        /** @param array<string> $with */
        public array $with,
        public bool $required,
    ) {}

    public function validate(
        InputValueDefinitionNode $definitionNode,
        FieldDefinitionNode|InputObjectTypeDefinitionNode $parentNode,
    ): void {
        $nodeName = $definitionNode->name->value;
        $parentNodeName = $parentNode->name->value;
        $valueType = $this->valueType($definitionNode->type);

        if (! in_array($valueType, self::SUPPORTED_VALUE_TYPES, true)) {
            throw new DefinitionException(
                "@bind directive defined on `{$parentNodeName}.{$nodeName}` does not support value of type `{$valueType}`. Expected `" . implode('`, `', self::SUPPORTED_VALUE_TYPES) . '` or a list of one of these types.',
            );
        }

        if (! class_exists($this->class)) {
            throw new DefinitionException(
                "@bind argument `class` defined on `{$parentNodeName}.{$nodeName}` must be an existing class, received `{$this->class}`.",
            );
        }

        if ($this->isModelBinding()) {
            return;
        }

        if (method_exists($this->class, '__invoke')) {
            return;
        }

        $modelClass = Model::class;
        throw new DefinitionException(
            "@bind argument `class` defined on `{$parentNodeName}.{$nodeName}` must extend {$modelClass} or define the method `__invoke`, but `{$this->class}` does neither.",
        );
    }

    private function valueType(TypeNode $typeNode): string
    {
        if (property_exists($typeNode, 'type')) {
            return $this->valueType($typeNode->type);
        }

        if ($typeNode instanceof NamedTypeNode) {
            return $typeNode->name->value;
        }

        return '';
    }

    public function isModelBinding(): bool
    {
        return is_subclass_of($this->class, Model::class);
    }
}
