<?php


namespace Nuwave\Lighthouse\Support\Contracts\GraphQl\Repositories;


use Nuwave\Lighthouse\Support\Contracts\GraphQl\Node;

interface NodeRepository
{
    public function fromDriver($node) : Node;
}