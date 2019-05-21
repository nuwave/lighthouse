# Integration Testing

When writing integration tests for your GraphQL API, we recommend you to
add the following helper method

```php
public function graphql(string $query)
{
    return $this->post(config('lighthouse.route_name'), [
        'query' => $query
    ]);
}
```
This method is a simple wrapper for making a request to your API.\
This means we can now send a request by simply doing
```php
$response = $this->graphql("{users(count: 10) { data {name} }");
```
And get the result by
```php
$response->json();
```

## Assertions
As we are writing test, we want to make assertions of the data.\
For doing this we can utilize that the results is json, so we just have
to find the data in the json response.\
\
Given the query from before
```php
$response = $this->graphql("{users(count: 10) { data {name} }");
```
We can get all of the names by doing

```php
$names = $response->json("data.*.name");
```

So a full test method could look like
```php
/** @test */
public function can_get_names_from_users()
{
    $userA = factory(User::class)->create(['name' => 'A']);
    $userB = factory(User::class)->create(['name' => 'B']);
    $userC = factory(User::class)->create(['name' => 'C']);

    $response = $this->graphql("{users(count: 10) { data {name} }");

    $names = $response->json("data.*.name");
    $this->assertCount(3, $names);
    $this->assertArraySubset(
        $names,
        [
            $userA->name,
            $userB->name,
            $userC->name,
        ]
    );
}

```