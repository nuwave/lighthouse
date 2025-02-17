<?php declare(strict_types=1);

namespace Nuwave\Lighthouse\Bind;

use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use Illuminate\Database\Eloquent\Model;
use Nuwave\Lighthouse\Exceptions\DefinitionException;

/** @template-covariant TClass of object */
class BindDefinition
{
    public function __construct(
        /** @var class-string<TClass> */
        // @phpstan-ignore generics.variance (TODO make class readonly)
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

        if (! class_exists($this->class)) {
            throw new DefinitionException("@bind argument `class` defined on `{$parentNodeName}.{$nodeName}` must be an existing class, received `{$this->class}`.");
        }

        if ($this->isModelBinding()) {
            return;
        }

        if (method_exists($this->class, '__invoke')) {
            return;
        }

        $modelClass = Model::class;
        throw new DefinitionException("@bind argument `class` defined on `{$parentNodeName}.{$nodeName}` must extend {$modelClass} or define the method `__invoke`, but `{$this->class}` does neither.");
    }

    public function isModelBinding(): bool
    {
        return is_subclass_of($this->class, Model::class);
    }
}
