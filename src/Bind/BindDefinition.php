<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\TypeNode;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

use function class_exists;
use function implode;
use function in_array;
use function is_callable;
use function is_subclass_of;
use function property_exists;

/**
 * @template TClass
 * @property-read class-string<TClass> $class
 * @property-read string $column
 * @property-read array<string> $with
 * @property-read bool $optional
 */
class BindDefinition
{
    private const SUPPORTED_VALUE_TYPES = ['ID', 'String', 'Int'];

    /**
     * @param class-string<TClass> $class
     * @param array<string> $with
     */
    public function __construct(
        public string $class,
        public string $column,
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
                "@bind directive defined on `$parentNodeName.$nodeName` does not support value of type `$valueType`. " .
                "Expected `" . implode('`, `', self::SUPPORTED_VALUE_TYPES) . '` or a list of one of these types.'
            );
        }

        if (! class_exists($this->class)) {
            throw new DefinitionException(
                "@bind argument `class` defined on `$parentNodeName.$nodeName` " .
                "must be an existing class, received `$this->class`.",
            );
        }

        if ($this->isModelBinding()) {
            return;
        }

        if (is_callable($this->class)) {
            return;
        }

        throw new DefinitionException(
            "@bind argument `class` defined on `$parentNodeName.$nodeName` must be " .
            "an Eloquent model or a callable class, received `$this->class`.",
        );
    }

    private function valueType(TypeNode $typeNode): string
    {
        if (property_exists($typeNode, 'type')) {
            return $this->valueType($typeNode->type);
        }

        return $typeNode->name->value;
    }

    public function isModelBinding(): bool
    {
        return is_subclass_of($this->class, Model::class);
    }
}
