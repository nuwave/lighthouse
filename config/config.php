<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Namespace registry
    |--------------------------------------------------------------------------
    |
    | This package provides a set of commands to make it easy for you to
    | create new parts in your GraphQL schema. Change these values to
    | match the namespaces you'd like each piece to be created in.
    |
    */
    'namespaces' => [
        'mutations'   => 'App\\Http\\GraphQL\\Mutations',
        'queries'     => 'App\\Http\\GraphQL\\Queries',
        'types'       => 'App\\Http\\GraphQL\\Types',
        'fields'      => 'App\\Http\\GraphQL\\Fields',
        'connections' => 'App\\Http\\GraphQL\\Connections',
        'dataloaders' => 'App\\Http\\GraphQL\\DataLoaders',
    ],

    'cache' => storage_path('lighthouse/cache'),
    'controller' => 'Nuwave\Lighthouse\Support\Http\Controllers\LaravelController@query',
    'pagination_macro' => 'toConnection',
    'route' => [],
    'model_path' => 'App\\Models',
    'camel_case' => false,

    'globalId' => [
        'encode' => null,
        'decodeId' => null,
        'decodeType' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema declaration
    |--------------------------------------------------------------------------
    |
    | This is a path that points to where your Relay schema is located
    | relative to the app path. You should define your entire Relay
    | schema in this file. Declare any Relay queries, mutations,
    | and types here instead of laravel-graphql config file.
    |
    */

    'schema' => [
        'output' => storage_path('lighthouse/schema.json'),
        'register' => function () {
            //
        },
    ],
];
