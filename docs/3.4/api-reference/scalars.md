# Scalars

You can use Lighthouse's built-in scalars by defining them in your schema,
using [`@scalar`](directives.md#scalar) to point them to a FQCN.

```graphql
"A datetime string with format `Y-m-d H:i:s`, e.g. `2018-01-01 13:00:00`."
scalar DateTime @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")

type Query {
  "Get the local server time."
  now: DateTime!
}
```

## Date

```graphql
"A date string with format `Y-m-d`, e.g. `2011-05-23`."
scalar Date @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\Date")
```

Internally represented as an instance of `Carbon\Carbon`.

## DateTime

```graphql
"A datetime string with format `Y-m-d H:i:s`, e.g. `2018-01-01 13:00:00`."
scalar DateTime @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")
```

Internally represented as an instance of `Carbon\Carbon`.

## Upload

```graphql
"Can be used as an argument to upload files using https://github.com/jaydenseric/graphql-multipart-request-spec" 
scalar Upload @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\Upload")
```

This Scalar can only be used as an argument, not as a return type.
For more information, please refer to the [file uploads guide](../guides/file-uploads.md).

The multipart form request is handled by Lighthouse, the resolver gets passed
an instance of [`\Illuminate\Http\UploadedFile`](https://laravel.com/api/5.8/Illuminate/Http/UploadedFile.html)
in the argument `array $variables`.
