<?php

namespace Nuwave\Lighthouse\Support\Interfaces;

use GraphQL\Type\Definition\ResolveInfo;

interface RelayMutation
{
    /**
     * List of output fields.
     *
     * @return array
     */
    public function outputFields();

    /**
     * Perform mutation.
     *
     * @param  array $input
     * @param  mixed $context
     * @param  \GraphQL\Type\Definition\ResolveInfo $info
     * @return array
     */
    public function mutateAndGetPayload(array $input, $context, ResolveInfo $info);
}
