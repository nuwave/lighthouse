<?php

use Nuwave\Lighthouse\Tests\Support\GraphQL\Types\UserType;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Queries\UserQuery;
use Nuwave\Lighthouse\Tests\Support\GraphQL\Mutations\UpdateEmailMutation;

GraphQL::schema()->type('userConfig', UserType::class);
GraphQL::schema()->query('userQueryConfig', UserQuery::class);
GraphQL::schema()->mutation('updateEmailConfig', UpdateEmailMutation::class);
