<?php


namespace Nuwave\Lighthouse\Support\Contracts\GraphQl;


use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Contracts\NodeMiddleware;
use Nuwave\Lighthouse\Support\Contracts\Resolver;
use Nuwave\Lighthouse\Support\Exceptions\DirectiveException;

interface Node
{
    public function toGraphQlNode();

    public function definitions() : Collection;

    public function directives() : Collection;

    public function directive($name) : Node;

    public function args() : Collection;

    public function arg($name) : ?string;

    public function kind(): int;

    public function name(): string;

    public function fields(): Collection;

    public function interfaces(): Collection;

    /**
     * @return NodeMiddleware
     * @throws DirectiveException
     */
    public function resolver() : NodeMiddleware;

    public function hasResolver() : bool;

    public function middlewares() : Collection;

    public function toType() : Type;

    public function description() : string;
}