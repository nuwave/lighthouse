<?php

if (! function_exists('graphql')) {
    /**
     * Get instance of graphql container.
     *
     * @return \Nuwave\Lighthouse\GraphQL
     */
    function graphql()
    {
        return app('graphql');
    }
}

if (! function_exists('schema')) {
    /**
     * Get instance of schema container.
     *
     * @return \Nuwave\Lighthouse\Schema\SchemaBuilder
     */
    function schema()
    {
        return graphql()->schema();
    }
}

if (! function_exists('directives')) {
    /**
     * Get instance of directives container.
     *
     * @return \Nuwave\Lighthouse\Schema\DirectiveFactory
     */
    function directives()
    {
        return graphql()->directives();
    }
}
