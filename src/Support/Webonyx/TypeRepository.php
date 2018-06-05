<?php


namespace Nuwave\Lighthouse\Support\Webonyx;


use Closure;
use Exception;
use GraphQL\Type\Definition\InterfaceType as WebonyxInterfaceType;
use GraphQL\Type\Definition\ObjectType as WebonyxObjectType;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Repositories\TypeRepository as TypeRepositoryInterface;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Type as TypeInterface;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Types\InterfaceType;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Types\ObjectType;

class TypeRepository implements TypeRepositoryInterface
{
    public function fromDriver($type): TypeInterface
    {
        if($type instanceof TypeInterface) {
            throw new Exception();
        }
        return new Type($type);
    }

    public function create(string $type, string $name, Closure $fields, Closure $resolve = null, string $description = null): TypeInterface
    {
        switch ($type) {
            case InterfaceType::class:
                return $this->fromDriver(new WebonyxInterfaceType([
                    'name' => $name,
                    'description' => $description,
                    'fields' => function() use ($fields) {
                        return $fields();
                    },
                    'resolveType' => $resolve
                ]));
            case ObjectType::class:
                return $this->fromDriver(new WebonyxObjectType([
                    'name' => $name,
                    'fields' => function() use ($fields) {
                        return $fields()->map(function (\Nuwave\Lighthouse\Support\Contracts\GraphQl\Node $node) {
                            return $node->toGraphQlNode();
                        })->all();
                    }
                ]));
        }
    }
}