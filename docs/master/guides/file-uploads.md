# File Uploads
::: tip
The logic for handling file uploads is located in the GraphQLController.
You must include that logic if you use a custom controller.
:::

Lighthouse conforms to the [graphql-multipart-request-spec](https://github.com/jaydenseric/graphql-multipart-request-spec)
which use multipart/form-requests to transfer files in the same request as the query.

This allows for file uploads through mutations.

## Preparing your schema

In order to accept file uploads, you must add the `Upload` scalar to your schema.

```graphql
scalar Upload @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\Upload")
```

Once the scalar is added, you can add it to a mutation.

````graphql
type Mutation {
    addProfilePicture(file: Upload!): ProfilePicture @field(resolver: "App\\GraphQL\\Mutations\\Models\\ProfilePictureMutator@add")
}
````

## Uploading a file

In order to upload a file, you must send a multipart/form-request.

The request must contain a payload structured like this:

```
--------------------------cec8e8123c05ba25 
Content-Disposition: form-data; name="operations"

{ "query": "mutation ($file: Upload!) { singleUpload(file: $file) { id } }", "variables": { "file": null } }
--------------------------cec8e8123c05ba25
Content-Disposition: form-data; name="map"

{ "0": ["variables.file"] }
--------------------------cec8e8123c05ba25
Content-Disposition: form-data; name="0"; filename="a.txt"
Content-Type: text/plain

Alpha file content.

--------------------------cec8e8123c05ba25--
```

Please note that the variable `file` is also defined inside `operations.variables`, but have the value `null`.
This value will be overwritten with the actual file by Lighthouse.

**Explanation:**

| Field         | Description |
| ------------- | :---------- | 
| operations    | Contains the data a normal query would contain; `query`, `variables` and `operationName`. | 
| map           | Contains a map of where, inside the `variables`, files should be added. | 
| 0, 1, 2 ...   | Each field contains a file. File 1 = `0` and so on. | 

On top of this, the header `content-type` must have the value `multipart/form` on all requests of this type.

**cURL example:**
```
curl lighthouse-project.dev/graphql \
  -F operations='{ "query": "mutation ($file: Upload!) { singleUpload(file: $file) { id } }", "variables": { "file": null } }' \
  -F map='{ "0": ["variables.file"] }' \
  -F 0=@a.txt
```
