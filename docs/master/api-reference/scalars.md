# Scalars
Scalars will parse the input and serialize the output of attributes and nodes of said type.

**Schema example:**
```graphql
scalar DateTime @scalar(class: "Nuwave\\Lighthouse\\Schema\\Types\\Scalars\\DateTime")

type Appointment {
    title: String!
    date: DateTime!
    created_at: DateTime!
    updated_at: DateTime!
}

type Query {
    appointments: [Appointment] @paginate(type: "connection")
}

type Mutation {
    addAppointment(text: String!, date: DateTime!): Appointment @create
}

```

## Date
**Parse:**

Expects an ISO 8601 date string (`Y-m-d`), eg.: `2019-01-15`. Parses to a Carbon-object.

**Serialize:**

Convert a date or Carbon-object to an ISO 8601 date string, eg. `2019-01-15`.

## DateTime

**Parse:**

Expects an ISO 8601 datetime string, eg.: `2019-01-15T23:15:33`. Parses to a `Carbon`-object.

**Serialize:**

Converts a date or Carbon-object to an ISO 8601 datetime string, eg. `2019-01-15T23:15:33`.

## Upload
Enables file uploads on attributes of type `Upload`.
For more information, please refer to the [File Uploads](../guides/file-uploads.md) guide.

**Parse:**

Injects the file into the variables array. The file will be available on the attribute.
Parses to a [`UploadedFile`](https://laravel.com/api/5.6/Illuminate/Http/UploadedFile.html)-object

**Serialize:**

Not supported.
