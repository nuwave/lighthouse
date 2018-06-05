<?php


namespace Nuwave\Lighthouse\Support\Webonyx;


use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Schema\Factories\DirectiveFactory;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Kind;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Node as NodeInterface;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Type;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\Resolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

class Node implements NodeInterface
{
    protected $node;

    /**
     * Type constructor.
     *
     * @param $node
     */
    public function __construct($node)
    {
        $this->node = $node;
    }

    public function toGraphQlNode()
    {
        return $this->node;
    }

    public function definitions(): Collection
    {
        return collect($this->node->definitions)->map(function ($node) {
            return graphql()->nodeRepository()->fromDriver($node);
        });
    }

    public function kind(): int
    {
        switch ($this->node->kind) {
            case "InterfaceTypeDefinition":
                return Kind::Interface;
            case "ObjectTypeDefinition":
                return Kind::Object;
        }
        dd("kind missing", $this->node->kind);
        return -1;
    }

    public function name(): string
    {
        return data_get($this->node, 'name.value');
    }

    public function directives(): Collection
    {
        return collect($this->node->directives)->map(function (DirectiveNode $node) {
            return graphql()->nodeRepository()->fromDriver($node);
        });
    }

    /**
     * @return NodeMiddleware
     * @throws DirectiveException
     */
    public function resolver(): NodeMiddleware
    {
        return directives()->handler($this->name());
    }

    public function hasResolver(): bool
    {
        try{
            return !is_null($this->resolver());
        } catch (DirectiveException $exception) {
            return false;
        }
    }

    public function middlewares(): Collection
    {
       return $this->directives()->map(function (NodeInterface $node) {
           return $node->resolver();
       });
    }

    public function directive($name): NodeInterface
    {
        return $this->directives()->first(function (Node $node) use ($name) {
            return $node->name() === $name;
        });
    }

    public function args(): Collection
    {
        return collect($this->node->arguments)->map(function (ArgumentNode $node) {
            return [$node->name->value => $node->value->value];
        })->flattenKeepKeys(1);
    }

    public function arg($name): ?string
    {
        return $this->args()->first(function ($value, $key) use ($name) {
            return $key === $name;
        });
    }

    public function fields(): Collection
    {
        return collect($this->node->fields)->map(function ($field) {
            return graphql()->nodeRepository()->fromDriver($field);
        });
    }

    public function toType(): Type
    {
        switch ($this->node->kind) {
            case "InterfaceTypeDefinition":
                return graphql()->typeRepository()->create(
                    \Nuwave\Lighthouse\Support\Contracts\GraphQl\Types\InterfaceType::class,
                    $this->name(),
                    function () {
                        return $this->fields();
                    }
                );
            case "ObjectTypeDefinition":
                return graphql()->typeRepository()->create(
                    \Nuwave\Lighthouse\Support\Contracts\GraphQl\Types\ObjectType::class,
                    $this->name(),
                    function () {
                        return $this->fields();
                    }
                );
        }
    }

    public function description(): string
    {
        return trim(str_replace("\n", '', $this->node->description));
    }

    public function interfaces(): Collection
    {
        return collect($this->node->interfaces);
    }
}