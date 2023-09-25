# Best Practices

When starting out with developing a GraphQL API, it is a good idea to look at existing best practices.
We recommend you use [GraphQL Rules](https://graphql-rules.com) and the following tips as a starting point
to develop a set of guidelines that works for you.

## In the mutation response, return a field of type Query

If you decide to [return the `Query` object in every mutation payload](https://graphql-rules.com/rules/mutation-payload-query),
Lighthouse makes it very easy.

```graphql
type Mutation {
  likePost(id: ID!): LikePostResult!
}

type LikePostResult {
  record: Post!
  query: Query!
}
```

Lighthouse automatically resolves the `Query` type, your mutation resolver only has to focus on its specific work.

```php
namespace App\GraphQL\Mutations;

final class LikePost
{
    /** @param  array{id: string}  $args */
    public function __invoke(mixed $root, array $args): array
    {
        // do the main work

        return ['record' => $post];
    }
}
```
