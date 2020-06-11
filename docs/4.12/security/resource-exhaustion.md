# Preventing Resource Exhaustion

GraphQL gives enormous power to clients. But with great power come great responsibilities 🕷.

Since clients have the possibility to craft very complex queries, our servers must be ready
to handle them properly. These queries may be abusive queries from evil clients,
or may simply be very large queries used by legitimate clients.
In both of these cases, the client can potentially take your GraphQL server down.

_This intro was taken from HowToGraphQL, we recommend reading their full chapter on security https://www.howtographql.com/advanced/4-security/_

You can utilize the built-in security options through `config/lighthouse.php`.
Read up on [the security options offered by webonyx/graphql-php](http://webonyx.github.io/graphql-php/security/)

If you implemented some additional security feature, we are welcoming contributions!
