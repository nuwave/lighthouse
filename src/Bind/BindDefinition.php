<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Type\Definition\Type;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;
use Nuwave\Lighthouse\Schema\AST\ASTHelper;

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
        /** @var class-string<TClass> */
        public string $class,
        public string $column,
        /** @var array<string> */
        public array $with,
        public bool $required,
    ) {}

    public function validate(
        InputValueDefinitionNode $definitionNode,
        FieldDefinitionNode|InputObjectTypeDefinitionNode $parentNode,
    ): void {
        $nodeName = $definitionNode->name->value;
        $parentNodeName = $parentNode->name->value;
        $typeName = ASTHelper::getUnderlyingTypeName($definitionNode);

        if (! in_array($typeName, self::SUPPORTED_VALUE_TYPES, true)) {
            throw new DefinitionException(
                "@bind directive defined on `{$parentNodeName}.{$nodeName}` does not support value of type `{$typeName}`. Expected `" . implode('`, `', self::SUPPORTED_VALUE_TYPES) . '` or a list of one of these types.',
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

    public function isModelBinding(): bool
    {
        return is_subclass_of($this->class, Model::class);
    }
}
