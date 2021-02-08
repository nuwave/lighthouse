# Request Lifecycle

To understand how queries are executed, we can examine the lifecycle of an incoming GraphQL request.
This section should provide an understanding of the involved steps, their order and purpose.

## Routing

All requests to the configured GraphQL endpoint - usually `/graphql` - are routed
to the single `GraphQLController`.

## HTTP Middleware

We are still dealing with HTTP requests, so Laravel middleware applies as usual.

## Request Parsing

The incoming request is parsed into the elements that make a GraphQL request.
Lighthouse aims to handle requests following [the informal specification at graphql.org](https://graphql.org/learn/serving-over-http/).

## Schema Construction

The defined types are collected from the `.graphql` schema files and programmatic definitions.
Schema transformations are applied, such as expansion of `@paginate` or `@orderBy`.

Lighthouse assembles a runtime representation - the executable schema.
This step is typically cached for larger schemas, to enhance performance.

## Query Validation

The GraphQL query is validated to ensure it matches the schema. Lighthouse makes sure the requested
fields are available in the schema, and the correct variables are passed.

## Field Execution

Starting from the root level, the fields within the query are executed.

Each field may be wrapped with field middleware, which can add authentication, authorization,
fine-grained validation, and more. Finally, the field resolver is called to produce a value for the field.

If the field contains a subselection, the same process happens for the nested fields, until
we either abort with an error or traversed the entire query tree.

[Learn more about GraphQL execution](https://graphql.org/learn/execution/).

## Error Handling

If an error occurs during field execution, the traversal of that particular subtree is stopped.
Lighthouse collects the errors to allow the rest of the query to execute.

## Result Assembly

The execution results are assembled into a structure that resembles the query of the client.
Errors are properly formatted and included in the response. The response is sent to the client.
