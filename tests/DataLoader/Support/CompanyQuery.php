<?php

namespace Nuwave\Lighthouse\Tests\DataLoader\Support;

use GraphQL;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Definition\GraphQLQuery;
use Nuwave\Lighthouse\Tests\Support\Models\Company;

class CompanyQuery extends GraphQLQuery
{
    /**
     * Type query returns.
     *
     * @return Type
     */
    public function type()
    {
        return GraphQL::type('company');
    }

    /**
     * Available query arguments.
     *
     * @return array
     */
    public function args()
    {
        return [
            'id' => [
                'type' => Type::nonNull(Type::string()),
            ],
        ];
    }

    /**
     * Resolve the query.
     *
     * @param  mixed  $root
     * @param  array  $args
     * @param  mixed  $context
     * @param  ResolveInfo  $info
     * @return mixed
     */
    public function resolve($root, array $args, $context, ResolveInfo $info)
    {
        $company = Company::find($this->decodeRelayId($args['id']));
        $fields = graphql()->fieldParser()->fetch($info);

        return app(CompanyDataFetcher::class)->resolve($company, $fields);
    }
}
