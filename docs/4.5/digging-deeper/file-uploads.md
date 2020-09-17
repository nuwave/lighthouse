# Uploading files

Lighthouse allows you to upload files using a multipart form request
as defined in [graphql-multipart-request-spec](https://github.com/jaydenseric/graphql-multipart-request-spec).

## Setup

In order to accept file uploads, you must add the `Upload` scalar to your schema.

```graphql
"Can be used as an argument to upload files using https://github.com/jaydenseric/graphql-multipart-request-spec"
scalar Upload
  @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\Upload")
```

Once the scalar is added, you can add it to a mutation.

```graphql
type Mutation {
  "Upload a file that is publicly available."
  upload(file: Upload!): String
}
```

## Handling file uploads

Lighthouse accepts multipart form requests that contain file uploads.
The given file is injected into the `array $variables` as an instance of [`\Illuminate\Http\UploadedFile`](https://laravel.com/api/5.8/Illuminate/Http/UploadedFile.html)
and passed into the resolver.

It is up to you how to handle the given file in the resolver,
see the [Laravel docs for File Uploads](https://laravel.com/docs/filesystem#file-uploads).

The field from the previous example can be implemented like this:

```php
<?php

namespace App\GraphQL\Mutations;

class Upload
{
    /**
     * Upload a file, store it on the server and return the path.
     *
     * @param  mixed  $root
     * @param  array<string, mixed>  $args
     * @return string|null
     */
    public function __invoke($root, array $args): ?string
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $args['file'];

        return $file->storePublicly('uploads');
    }
}
```

## Client-side Usage

In order to upload a file, you must send a `multipart/form-data` request.
Use any of the [available client implementations](https://github.com/jaydenseric/graphql-multipart-request-spec#client)
or look at the [specification examples](https://github.com/jaydenseric/graphql-multipart-request-spec#multipart-form-field-structure) to roll your own.

To test the example above, prepare a file you can upload.

```bash
echo "test content" > my_file.txt
```

Then, send a request to upload the file to your server:

```bash
curl localhost/graphql \
  -F operations='{ "query": "mutation ($file: Upload!) { upload(file: $file) }", "variables": { "file": null } }' \
  -F map='{ "0": ["variables.file"] }' \
  -F 0=@my_file.txt
```

## Testing

Refer to [testing file uploads in PHPUnit](../testing/phpunit.md#simulating-file-uploads).
