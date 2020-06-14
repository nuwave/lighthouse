# Request Lifecycle

To understand how queries are executed, we can examine the lifecycle of an incoming GraphQL request.
This section should provide an understanding of the involved steps, their order and purpose.

## Routing

All requests to the configured GraphQL endpoint - usually `/graphql` - are routed
to the single `GraphQLController`.

## Request Parsing

The incoming request is parsed into the elements that make a GraphQL request.
Lighthouse aims to handle requests following [the informal specification at graphql.org](https://graphql.org/learn/serving-over-http/).

## Schema Construction

The defined types are assembled into an executable schema.
