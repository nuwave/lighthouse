<?php


namespace Nuwave\Lighthouse\Support\Webonyx;


use Nuwave\Lighthouse\Support\Contracts\GraphQl\Node as NodeInterface;
use Nuwave\Lighthouse\Support\Contracts\GraphQl\Repositories\NodeRepository as NodeRepositoryInterface;
use Nuwave\Lighthouse\Support\Webonyx\Node;

class NodeRepository implements NodeRepositoryInterface
{

    public function fromDriver($node): NodeInterface
    {
        return new Node($node);
    }
}