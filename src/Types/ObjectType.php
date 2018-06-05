<?php


namespace Nuwave\Lighthouse\Types;


use Closure;
use Illuminate\Support\Collection;
use Nuwave\Lighthouse\Support\Pipeline;

class ObjectType extends Type
{
    /**
     * Returns all fields after manipulated by the directives
     *
     * @return Collection
     */
    public function fields(): Collection
    {
        parent::fields();
        // Sends the collection returned by field closure through the pipeline of node directives.
        return app(Pipeline::class)
            ->send($this)
            ->through(graphql()->directives()->getFromDirectives($this->directives()))
            ->via('handleNode')
            ->then(function(Type $type) {
                return $type->resolvedFields();
            });
    }


}